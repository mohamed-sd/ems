<?php
/**
 * tools/ceo_account_access_probe.php — أثمّةَ حسابٌ للرئيسِ التنفيذيِّ يدخل ويرى؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثلاثةُ أسئلةٍ لا سؤالٌ واحد**، وكلٌّ منها قد يُجيبُ «نعم» والبابُ مغلق:
 *   ① أثمّةَ حسابٌ حيٌّ بدورِ الرئيس؟ (وجودٌ وحالةٌ وكلمةُ مرورٍ مضبوطة)
 *   ② أيَملِك رؤيةَ الأسطحِ الخمسةِ والثلاثين؟ (‏الحارسُ لا جدولُ الأدوار)
 *   ③ أتظهر له في السايدبار؟ (‏موضعُ الملاحةِ غيرُ الصلاحيّة)
 *
 * ⛔ **ولا يُقاس الإذنُ بجدولِ `role_permissions`**: بين المستخدمِ والقاعدةِ
 *   طبقةُ قوالبَ (`gov_authority_grants`) تغلبُ الدورَ — «مغطًّى بقالبٍ نافذٍ
 *   يُحكَم بقالبِه حصرًا». فالمِسبارُ **يُنصِّب جلسةَ المستخدمِ ويستدعي
 *   `check_page_permissions()` نفسَها** التي تستدعيها الشاشة.
 *
 * ⛔ **ولا يُدخِل هذا المِسبارُ كلمةَ مرورٍ ولا يصادق**: يقيس الاستحقاقَ لا
 *   يُجرِّب الدخول — تجربةُ الدخولِ فعلُ صاحبِ الحساب.
 *
 * التشغيل: php tools/ceo_account_access_probe.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('تعذّر الاتصال: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;

$_SESSION = array();
require_once $ROOT . '/includes/permissions_helper.php';

/* ═══ ① الحساب — أموجودٌ وحيٌّ؟ ══════════════════════════════════════════ */
$CEO_ROLE = 9;                       /* «الإدارة التنفيذية» — ملاحظتُها في `roles` تقول: الرئيسُ التنفيذيّ */
echo "◆ ① الحساب — الدورُ 9 «الإدارة التنفيذية»:\n";
$accs = array();
$rs = $conn->query("SELECT u.id, u.username, u.company_id, u.status, u.employee_id,
                           (u.password IS NOT NULL AND u.password <> '') AS has_pw,
                           e.name AS emp_name
                      FROM users u LEFT JOIN employees e ON e.id = u.employee_id
                     WHERE u.role = $CEO_ROLE OR u.role_id = $CEO_ROLE
                     ORDER BY u.company_id, u.id");
while ($x = $rs->fetch_assoc()) { $accs[] = $x; }
if (!$accs) { echo "   ⛔ لا حسابَ بهذا الدورِ إطلاقًا\n"; }
foreach ($accs as $a) {
    printf("   #%-4d %-16s شركة=%-3d حالة=%-8s كلمةُ مرور=%-6s موظّف=%s\n",
        $a['id'], $a['username'], $a['company_id'], $a['status'],
        $a['has_pw'] ? 'مضبوطة' : '⛔ فارغة', $a['emp_name'] ?: '—');
}

/* ═══ ② الرؤية — بالحارسِ نفسِه لا بجدولِ الأدوار ═════════════════════════ */
$SCREENS = array(
    'الرئيس' => array(
        'Portal/exec_command_board.php', 'Portal/exec_org_projects.php', 'Portal/exec_daily_report.php',
        'Portal/exec_daily_stops.php', 'Portal/exec_daily_deviations.php', 'Portal/exec_weekly_report.php',
        'Portal/exec_monthly_pack.php', 'Portal/ceo_unified_queue.php', 'Portal/exec_financial_approvals.php',
        'Portal/exec_document_approvals.php', 'Portal/exec_doc_review_notes.php', 'Portal/exec_contract_registry.php',
        'Portal/exec_redline_breaches.php', 'Portal/exec_reserved_matters.php', 'Portal/exec_critical_exceptions.php',
        'Portal/ceo_assurance_box.php', 'Portal/exec_escalations.php', 'Portal/exec_crisis_command.php',
        'Portal/exec_strategic_decisions.php', 'Portal/project_charter.php', 'Portal/ceo_org_decisions.php',
        'Portal/exec_temp_assignments.php', 'Portal/exec_leadership_appointments.php',
        'Portal/exec_leadership_meetings.php', 'Portal/exec_meeting_decisions.php', 'Portal/exec_actions_followup.php',
    ),
    'النائب' => array(
        'Portal/vp_dashboard.php', 'Portal/vp_departments.php', 'Portal/vp_daily_report.php',
        'Portal/vp_weekly_report.php', 'Portal/vp_monthly_review.php', 'Portal/vp_approval_inbox.php',
        'Portal/vp_pending_actions.php', 'Portal/vp_actions_followup.php', 'Portal/exec_delegations.php',
    ),
);

foreach ($accs as $a) {
    printf("\n◆ ② الرؤيةُ للحساب #%d «%s» — بجلسةٍ منصَّبةٍ واستدعاءِ الحارس:\n", $a['id'], $a['username']);
    $_SESSION['user'] = array('id' => (int) $a['id'], 'role' => (int) $CEO_ROLE,
                              'company_id' => (int) $a['company_id'], 'username' => $a['username']);
    foreach ($SCREENS as $space => $list) {
        $yes = 0; $unreg = array(); $deny = array();
        foreach ($list as $code) {
            $p = check_page_permissions($conn, $code);
            if (!empty($p['unregistered'])) { $unreg[] = basename($code); continue; }
            if (!empty($p['can_view'])) { $yes++; } else { $deny[] = basename($code); }
        }
        printf("   %-8s يرى %d من %d", $space, $yes, count($list));
        if ($unreg) { printf(" · غيرُ مسجَّلةٍ في `modules` (%d): %s", count($unreg), implode(' · ', $unreg)); }
        if ($deny)  { printf(" · مسجَّلةٌ ومحجوبةٌ (%d): %s", count($deny), implode(' · ', $deny)); }
        echo "\n";
    }
}
/* ═══ ②-ب الشاهدُ السالب — «٣٥ من ٣٥» بلا تمييزٍ رقمٌ بلا معنى ═══════════
   يُقاس حسابٌ لا يُفترَض أن يرى أسطحَ القيادةِ بالمِسبارِ نفسِه: فإن رآها
   كلَّها فالعطبُ في المِسبارِ أو الأبوابُ مفتوحةٌ للجميع — لا أنّ الرئيسَ
   مُصرَّحٌ له. ⛔ ولا يُعتمَد الأخضرُ قبل أن يُثبِتَ المقياسُ أنّه يُحمِّر. */
echo "\n◆ ②-ب الشاهدُ السالب — حساباتٌ خارجَ القيادةِ بالمِسبارِ نفسِه:\n";
$ctl = array();
$rs = $conn->query("SELECT u.id, u.username, u.role, u.company_id, r.name AS role_name
                      FROM users u JOIN roles r ON r.id = u.role
                     WHERE u.company_id = 4 AND u.status = 'active' AND u.role <> $CEO_ROLE
                     GROUP BY u.role ORDER BY u.role LIMIT 5");
while ($x = $rs->fetch_assoc()) { $ctl[] = $x; }
$allScr = array_merge($SCREENS['الرئيس'], $SCREENS['النائب']);
$discriminates = false;
foreach ($ctl as $c) {
    $_SESSION['user'] = array('id' => (int) $c['id'], 'role' => (int) $c['role'],
                              'company_id' => (int) $c['company_id'], 'username' => $c['username']);
    $yes = 0;
    foreach ($allScr as $code) {
        $p = check_page_permissions($conn, $code);
        if (empty($p['unregistered']) && !empty($p['can_view'])) { $yes++; }
    }
    if ($yes < count($allScr)) { $discriminates = true; }
    printf("   دور %-3d %-22s #%-4d يرى %d من %d\n",
        $c['role'], mb_substr($c['role_name'], 0, 20), $c['id'], $yes, count($allScr));
}
echo $discriminates
    ? "   ✔ المقياسُ يُحمِّر — فخُضرةُ الرئيسِ تصريحٌ مقيسٌ لا عمًى\n"
    : "   ⛔ كلُّ دورٍ يرى كلَّ شيءٍ — الرقمُ لا يدلُّ على تصريحٍ للرئيس\n";
$_SESSION['user'] = null;

/* ═══ ③ الملاحة — الصلاحيّةُ لا تضع الرابطَ في السايدبار ═════════════════ */
echo "\n◆ ③ الملاحةُ — أللأسطحِ موضعٌ يُظهرها؟ (‏الموضعُ غيرُ الصلاحيّة)\n";
$all = array_merge($SCREENS['الرئيس'], $SCREENS['النائب']);
$inMod = 0; $noMod = array();
foreach ($all as $code) {
    $st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
    $st->bind_param('s', $code);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) { $inMod++; } else { $noMod[] = basename($code); }
    $st->close();
}
printf("   في `modules` بالمسارِ التامّ: %d من %d\n", $inMod, count($all));
if ($noMod) { echo '   بلا صفٍّ بالمسارِ التامّ: ' . implode(' · ', $noMod) . "\n"; }

/* ⛔ ولا يُقاس ظهورُ الرابطِ بوجودِ صفٍّ في `nav_placements`: المُصيِّرُ يشترط
   مساحةً مربوطةً بالدورِ بـ`binding='PRIMARY'` ونوعِها EXECUTIVE/DEPARTMENT،
   ومجموعةً نافذةً في **`nav_lifecycle_groups`** (لا `link_groups`)، وصنفَ
   موضعٍ من `MENU_ITEM`/`LANDING_PAGE`. فالقياسُ باستعلامِ المُصيِّرِ حرفًا. */
$q = $conn->query("SELECT w.workspace_id, w.kind FROM nav_ws_roles wr
                     JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.active = 1
                    WHERE wr.role_id = $CEO_ROLE AND wr.binding = 'PRIMARY'
                      AND w.kind IN ('DEPARTMENT','EXECUTIVE') LIMIT 1");
$wsRow = $q ? $q->fetch_assoc() : null;
if (!$wsRow) {
    echo "   ⛔ لا مساحةَ مربوطةٌ بالدورِ 9 بـPRIMARY — فالسايدبارُ لا يبني له بابًا\n";
} else {
    printf("   مساحةُ الدور: %s (%s)\n", $wsRow['workspace_id'], $wsRow['kind']);
    $ws = $conn->real_escape_string($wsRow['workspace_id']);
    $in = "'" . implode("','", array_map(array($conn, 'real_escape_string'), $all)) . "'";
    $r = $conn->query("SELECT COUNT(DISTINCT p.route) FROM nav_placements p
                         JOIN nav_lifecycle_groups g ON g.id = p.group_id AND g.active = 1
                        WHERE p.workspace_id = '$ws' AND p.active = 1
                          AND p.placement_type IN ('MENU_ITEM','LANDING_PAGE')
                          AND p.route IN ($in)");
    printf("   يُصيَّر في سايدبارِ المساحةِ: %s من %d\n", $r ? $r->fetch_row()[0] : '؟', count($all));
    $r = $conn->query("SELECT g.label_ar, COUNT(DISTINCT p.route) n FROM nav_placements p
                         JOIN nav_lifecycle_groups g ON g.id = p.group_id AND g.active = 1
                        WHERE p.workspace_id = '$ws' AND p.active = 1
                          AND p.placement_type IN ('MENU_ITEM','LANDING_PAGE')
                          AND p.route IN ($in)
                        GROUP BY g.id, g.label_ar ORDER BY g.sort_no");
    while ($x = $r->fetch_assoc()) { printf("      · %-46s %d رابطًا\n", $x['label_ar'], $x['n']); }
}
