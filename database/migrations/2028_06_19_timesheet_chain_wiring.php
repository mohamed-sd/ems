<?php
/**
 * 2028_06_19 — وصلُ سلسلةِ التايم شيت · وإعادتُها إلى إدارةِ الموقع
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ المقيسُ بالتصييرِ الحيّ**: سلسلةُ إدخالِ ساعاتِ العملِ **مقطوعةٌ
 *   على ثلاثِ إدارات**، ولا دورَ واحدٌ يراها كاملةً:
 *     ① `timesheet_type`    (اختيارُ النوع)   ⇐ DEP-18 وحدَها
 *     ② `timesheet`         (إدخالُ الساعات)  ⇐ DEP-11 وحدَها
 *     ③ `timesheet_details` (التفاصيل)        ⇐ **لا موضعَ لها إطلاقًا**
 *     ④ `view_timesheet`    (العرض)           ⇐ DEP-11 وحدَها
 *   فالدورُ 5 يبدأ ولا يُكمل، والدورُ 1 يُكمل ولا يبدأ، والثالثةُ لا يراها أحد.
 *
 * ⭐ **وصاحبُ العملِ محرومٌ من رابطِه**: الدورُ **6 «إدارة الموقع»** (7 مستخدمين
 *   منهم `#9` واسمُه الوظيفيُّ «مدخل ساعات») هو **الوحيدُ ذو حقِّ الكتابةِ**
 *   (`can_add=1` · `can_edit=1`) على `timesheet` و`timesheet_type` — **ولا يرى
 *   واحدةً منها**. صلاحيّتُه كاملةٌ وبابُه مغلق.
 *   ◆ والسببُ: هجرةُ `org_v4` نقلت الدورَ 6 إلى DEP-12 بتسعَ عشرةَ شاشةَ موقعٍ
 *     **ولم تنقل معه عائلةَ التايم شيت** التي بقيت في DEP-11.
 *
 * ◆ **وهذا وصلٌ لا توسعة**: `role_permissions` و`gov_profile_items` تسمحان
 *   بالثلاثِ للدورِ 6 سلفًا — المضافُ **رابطٌ يُظهر ما هو مأذونٌ به**.
 *   والمنحُ الجديدُ الوحيدُ: الدور 5 على `timesheet` (يُكمل ما يبدأه)،
 *   والدور 6 على `view_timesheet` في قالبِه (منحتُه في الجدولِ قائمةٌ سلفًا).
 *
 * ◆ **وقرارُ الملكيّةِ من المالكِ لا من القياس**: أُقرَّ صراحةً 2026-09-08
 *   أنَّ مديرَ الموقعِ هو صاحبُ إدخالِ ساعاتِ العمل.
 * ⭐ والتجميدُ يُرفَع ويُعاد ويُقاس إغلاقُه. التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
use App\Services\Security\PolicyWriteService as P;

const APPROVER = 56;
$WHY  = 'وصل سلسلة التايم شيت وإعادتها إلى إدارة الموقع — بأمر المالك 2028_06_19';
$LOGF = $ROOT . '/database/migrations/.2028_06_19.out';
$buf  = "── وصلُ سلسلةِ التايم شيت ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$esc  = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };

$was = array();
foreach (array(P::FREEZE_GRANTS, P::FREEZE_ACTIVATION) as $s) {
    $was[$s] = (int) $one("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
}
$restore = function () use ($conn, $was, $WHY, &$buf) {
    foreach ($was as $s => $a) {
        $c = $conn->query("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
        if ($c && (int) $c->fetch_row()[0] === $a) { $buf .= "  ↩ «{$s}» على حالها ({$a})\n"; continue; }
        $r = P::setFreeze($conn, $s, $a, 'إعادة التجميد بعد الوصل — ' . $WHY, 0);
        $buf .= "  ↩ «{$s}» ⇐ {$a} : " . ($r['ok'] ? 'أُعيدت' : 'فشل: ' . $r['msg']) . "\n";
    }
};
$die = function ($m) use (&$buf, $restore, $LOGF) {
    $buf .= "  ✘ {$m}\n"; $restore();
    file_put_contents($LOGF, $buf . "رسبت\n"); exit(1);
};

/* الوجهات: [مساحة, مجموعة, مسارٌ مُسوًّى, مسارٌ بلاحقة, تسمية] */
$WIRE = array(
    array('DEP-12', 73,   'timesheet/timesheet_type',    'Timesheet/timesheet_type.php',    'تسجيل الوحدات وأنواعها'),
    array('DEP-12', 73,   'timesheet/timesheet',         'Timesheet/timesheet.php',         'سجل ساعات التشغيل اليومي'),
    array('DEP-12', 73,   'timesheet/timesheet_details', 'Timesheet/timesheet_details.php', 'تفاصيل الوحدة'),
    array('DEP-12', 73,   'timesheet/view_timesheet',    'Timesheet/view_timesheet.php',    'سجل الوحدات اليومية'),
    array('DEP-18', 1447, 'timesheet/timesheet',         'Timesheet/timesheet.php',         'سجل ساعات التشغيل اليومي'),
    array('DEP-11', 66,   'timesheet/timesheet_details', 'Timesheet/timesheet_details.php', 'تفاصيل الوحدة'),
);

/* ═══ ① المواضعُ الحاكمة ═══════════════════════════════════════════════════ */
$cW = 0;
foreach ($WIRE as $w) {
    list($ws, $gid, $nrm, $full, $lab) = $w;
    if ($one("SELECT placement_id FROM nav_workspace_placements
               WHERE workspace_id=" . $esc($ws) . " AND route=" . $esc($nrm) . " AND status='ACTIVE'")) { continue; }
    $nx = (int) $one("SELECT COALESCE(MAX(sort_no),0)+1 FROM nav_workspace_placements
                       WHERE workspace_id=" . $esc($ws) . " AND group_id={$gid}");
    $pid = 'WP-' . strtoupper(substr(md5($ws . '|ts|' . $nrm), 0, 16));
    if (!$conn->query("INSERT INTO nav_workspace_placements
        (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
         canonical_label, governing_source, source_ref, reason_code, effective_from,
         effective_to, status, version, created_by, approved_by, legacy_ref)
        VALUES (" . $esc($pid) . ",NULL," . $esc($ws) . ",{$gid},'PRIMARY',{$nx}," . $esc($nrm) . ","
        . $esc($lab) . ",'سلسلةُ التايم شيت تُوصَل — إدخالُ الساعاتِ لإدارةِ الموقع',"
        . $esc($WHY) . ",'TIMESHEET_CHAIN_WIRE',CURDATE(),NULL,'ACTIVE',1,
        'database/migrations/2028_06_19_timesheet_chain_wiring.php',NULL,NULL)")) {
        $die("موضعُ {$ws}/{$nrm} فشل: " . $conn->error);
    }
    $cW++;
}
$log("① مواضعُ أُضيفت = {$cW}");

/* ═══ ② المنحُ الناقصُ في الجدول ═══════════════════════════════════════════ */
$cR = 0;
foreach (array(array(5, 'Timesheet/timesheet.php'), array(5, 'Timesheet/timesheet_details.php')) as $g) {
    list($rid, $code) = $g;
    $mid = (int) $one("SELECT id FROM modules WHERE code=" . $esc($code));
    if (!$mid) { continue; }
    if ($one("SELECT id FROM role_permissions WHERE role_id={$rid} AND module_id={$mid}")) { continue; }
    $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  VALUES ({$rid},{$mid},1,0,0,0)");
    $cR++;
}
$log("② منحٌ في الجدول = {$cR}");

/* ═══ ③ إصدارُ قالبٍ لكلِّ دورٍ ينقصه بند ═════════════════════════════════ */
$NEED = array(
    6 => array('Timesheet/view_timesheet.php'),
    5 => array('Timesheet/timesheet.php', 'Timesheet/timesheet_details.php'),
);
$opened = false;
foreach ($NEED as $rid => $codes) {
    $p = $conn->query("SELECT DISTINCT p.profile_id, p.profile_code
        FROM users u JOIN gov_authority_grants g ON g.user_id=u.id AND g.revoked_at IS NULL
        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
        WHERE u.role={$rid} AND u.is_deleted=0 AND u.status='active' LIMIT 1")->fetch_assoc();
    if (!$p) { $log("③ دور {$rid}: لا قالبَ نافذًا يحمله أحد — تُخطّى"); continue; }
    $miss = array();
    foreach ($codes as $cd) {
        if (!$one("SELECT item_id FROM gov_profile_items WHERE profile_id={$p['profile_id']}
                    AND item_kind='screen' AND item_ref=" . $esc($cd) . " AND allow=1")) { $miss[] = $cd; }
    }
    if (!$miss) { $log("③ دور {$rid}: قالبُه كامل"); continue; }

    $base = preg_replace('~-S\d+$~', '', $p['profile_code']);
    $n = 4;
    while ($one("SELECT profile_id FROM gov_role_profiles WHERE profile_code=" . $esc($base . '-S' . $n))) { $n++; }
    $code = mb_substr($base . '-S' . $n, 0, 20);
    $r = P::cloneProfile($conn, (int) $p['profile_id'], $code, $WHY, APPROVER);
    if (!$r['ok']) { $die("نسخُ قالبِ الدور {$rid} فشل: " . $r['msg']); }
    $new = (int) $r['id'];
    foreach ($miss as $cd) {
        $x = P::setProfileItem($conn, $new, $cd,
            array('allow' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0),
            'بند من سلسلة التايم شيت — ' . $WHY, APPROVER);
        if (!$x['ok'] && $x['code'] !== 'NOTHING') { $die("بندُ «{$cd}» فشل: " . $x['msg']); }
    }
    $x = P::approveProfile($conn, $new, 'اعتماد إصدار يحمل سلسلة التايم شيت كاملة', APPROVER);
    if (!$x['ok'] && $x['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $x['msg']); }

    if (!$opened) {
        foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
            if ($was[$s] === 0) { continue; }
            $y = P::setFreeze($conn, $s, 0, 'نافذة صيانة لوصل سلسلة التايم شيت — ' . $WHY, APPROVER);
            if (!$y['ok']) { $die("فتحُ «{$s}» فشل: " . $y['msg']); }
        }
        $opened = true; $log('③ البوّابتان فُتحتا');
    }
    $x = P::activateProfile($conn, $new, $WHY, APPROVER);
    if (!$x['ok']) { $die('التفعيلُ فشل: ' . $x['msg']); }
    $hs = array();
    $q = $conn->query("SELECT grant_id, user_id FROM gov_authority_grants
                        WHERE profile_id={$p['profile_id']} AND revoked_at IS NULL");
    while ($q && ($h = $q->fetch_assoc())) { $hs[] = $h; }
    foreach ($hs as $h) {
        $y = P::revokeGrant($conn, (int) $h['grant_id'], 'ترحيل إلى ' . $code, APPROVER);
        if (!$y['ok']) { $die('السحبُ فشل: ' . $y['msg']); }
        $y = P::assignProfile($conn, (int) $h['user_id'], $new, $WHY, APPROVER);
        if (!$y['ok']) { $die('الإسنادُ فشل: ' . $y['msg']); }
    }
    P::retireProfile($conn, (int) $p['profile_id'], 'حلَّ محلَّه ' . $code, APPROVER);
    $log("③ دور {$rid}: {$p['profile_code']} ⇒ {$code} · بنودٌ أُضيفت=" . count($miss) . " · رُحِّل=" . count($hs));
}

$restore();

/* ═══ ④ تحقُّقٌ ذاتيّ ═══════════════════════════════════════════════════════ */
$pl = (int) $one("SELECT COUNT(*) FROM nav_workspace_placements
                   WHERE route LIKE 'timesheet/%' AND status='ACTIVE'");
$fz = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$log("④ مواضعُ التايم شيت النشطة = {$pl} · مجمَّدة={$fz} (المرجع " . count(array_filter($was)) . ")");
if ($fz !== count(array_filter($was))) { file_put_contents($LOGF, $buf . "البوّاباتُ لم تُعَد\n"); exit(1); }
file_put_contents($LOGF, $buf . "اكتمل الوصل\n");
exit(0);
