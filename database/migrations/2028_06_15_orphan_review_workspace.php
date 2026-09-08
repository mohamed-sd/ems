<?php
/**
 * 2028_06_15 — مساحةُ مراجعةِ الشاشاتِ اليتيمة · دورٌ وحسابٌ منفصلان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الطلب**: بابٌ في السايدبارِ فيه الشاشاتُ غيرُ المستعمَلةِ **مجموعةً
 *   بإدارتِها**، ليدخلَ المالكُ فيجرِّبَ كلَّ شاشةٍ ويقرِّرَ بشأنها.
 *
 * ⛔ **والقيدُ الذي حكم الشكل**: ظهورُ الرابطِ لا يكفي — الحارسُ يمنع الفتحَ
 *   ما لم تكن الشاشةُ في قالبِ صلاحيّاتِ الدور. فوضعُها في سايدبارِ كلِّ
 *   إدارةٍ كان **يوسّع صلاحيّاتِ سبعةَ عشرَ دورًا حيًّا** (‏المالية +53 شاشة)
 *   — وذاك عينُ ما فُتح تجميدُ `PERM-01` لمنعِه.
 * ⇒ **فالمراجعةُ في دورٍ منفصلٍ بحسابٍ منفصل**: صفرُ تغييرٍ على أيِّ دورٍ
 *   قائم، والتراجعُ بحذفِ الدورِ وحدَه. **وأربعٌ وعشرون مجموعةً = أربعٌ
 *   وعشرون إدارة** داخلَ سايدبارِه — فتتحقّق «ادخلْ كلَّ إدارةٍ» بلا توسعة.
 *
 * ◆ **والمصدرُ جدولٌ لا قائمةٌ مكتوبة**: كلُّ صفٍّ يُبنى من
 *   `gov_orphan_screens` الذي يملؤه `tools/orphan_screens_scan.php` بالقياسِ
 *   الحيّ. فإعادةُ المسحِ بعدَ إصلاحِ شاشةٍ تُخرجها من السجلِّ (`WIRED`)،
 *   وإعادةُ تشغيلِ هذه الهجرةِ تُزامن السايدبارَ معه.
 *
 * ⛔ **والتصريحُ على قدرِ ما يُحَلّ**: 267 شاشةً لها صفٌّ في `modules`
 *   فتدخل القالبَ؛ و53 بلا وحدةٍ تُصرَّح ببندِ `nav_items` بلا رمزِ فحصٍ
 *   (‏«بندٌ بلا وحدةٍ تحلُّ: حارسُه في وجهتِه لا في القائمة» — نصُّ المُصيِّر).
 *
 * ⭐ **والتجميدُ يُرفَع بإذنِ المالكِ ويُعاد في كلِّ مخرج** — والإغلاقُ يُقاس.
 * التشغيل: php database/migrate.php up
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/perm_change_log.php';
require_once $ROOT . '/app/Services/Security/PolicyWriteService.php';
use App\Services\Security\PolicyWriteService as P;

const WS       = 'WS-ORPHAN';
const CODE     = 'TGT-ORPHAN';
const APPROVER = 56;
const UNAME    = 'مراجعة الشاشات';
const UPASS    = '12345678';

$WHY = 'مساحة مراجعة الشاشات غير المستعملة — بأمر المالك 2028_06_15';
$LOGF = $ROOT . '/database/migrations/.2028_06_15.out';
$buf  = "── مساحةُ مراجعةِ الشاشاتِ اليتيمة ──\n";
$log  = function ($m) use (&$buf) { $buf .= "  {$m}\n"; };
$one  = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$esc  = function ($v) use ($conn) { return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'"; };

$was = array();
foreach (array(P::FREEZE_GRANTS, P::FREEZE_ACTIVATION) as $s) {
    $was[$s] = (int) $one("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
}
$restore = function () use ($conn, $was, $WHY, &$buf) {
    foreach ($was as $s => $a) {
        $c = $conn->query("SELECT active FROM gov_policy_freeze WHERE scope_code='" . $s . "'");
        if ($c && (int) $c->fetch_row()[0] === $a) { $buf .= "  ↩ «{$s}» على حالها ({$a})\n"; continue; }
        $r = P::setFreeze($conn, $s, $a, 'إعادة التجميد بعد نافذة الصيانة — ' . $WHY, 0);
        $buf .= "  ↩ «{$s}» ⇐ {$a} : " . ($r['ok'] ? 'أُعيدت' : 'فشل: ' . $r['msg']) . "\n";
    }
};
$die = function ($m) use (&$buf, $restore, $LOGF) {
    $buf .= "  ✘ {$m}\n"; $restore();
    file_put_contents($LOGF, $buf . "رسبت الهجرة — والبوابات أُعيدت\n"); exit(1);
};

/* ── المصدر: سجلُّ اليتامى ────────────────────────────────────────────────── */
$rows = array();
$r = $conn->query("SELECT route, route_norm, owner_dept, module_id, module_code, title_ar
                     FROM gov_orphan_screens WHERE decision IN ('PENDING','KEEP')
                    ORDER BY owner_dept, route");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
if (!$rows) { $die('سجلُّ اليتامى فارغ — شغِّل tools/orphan_screens_scan.php أوّلًا'); }
$log('① يتامى من السجل = ' . count($rows));

/* ═══ ② الدور ═════════════════════════════════════════════════════════════ */
$rid = (int) $one("SELECT id FROM roles WHERE name='مراجعة الشاشات غير المستعملة'");
if (!$rid) {
    if (!$conn->query("INSERT INTO roles (name, parent_role_id, level, role_scope, status)
                       VALUES ('مراجعة الشاشات غير المستعملة', NULL, 1, 'all', 1)")) {
        $die('إنشاءُ الدورِ فشل: ' . $conn->error);
    }
    $rid = (int) $conn->insert_id;
    $log("② الدور {$rid} أُنشئ");
} else { $log("② الدور {$rid} قائم"); }

/* ═══ ③ المساحةُ والربط ═══════════════════════════════════════════════════ */
if (!$one("SELECT workspace_id FROM nav_workspaces WHERE workspace_id='" . WS . "'")) {
    $conn->query("INSERT INTO nav_workspaces
        (workspace_id, kind, name_ar, dept_code, ruling, source_ref, active,
         workspace_code, canonical_name, workspace_type, owner_domain, governing_source, version)
        VALUES ('" . WS . "','DEPARTMENT','مراجعة الشاشات غير المستعملة',NULL,
        'مساحةُ مراجعةٍ مؤقّتةٌ — مجموعاتُها إداراتُ الشاشاتِ اليتيمةِ لا دورةُ عمل',"
        . $esc($WHY) . ",1,'" . WS . "','مراجعة الشاشات غير المستعملة','DEPARTMENT','" . WS . "',"
        . $esc($WHY) . ",1)");
    $log('③ المساحة ' . WS . ' أُنشئت');
}
if (!$one("SELECT role_id FROM nav_ws_roles WHERE workspace_id='" . WS . "' AND role_id={$rid}")) {
    $conn->query("INSERT INTO nav_ws_roles (workspace_id, role_id, binding, source_ref, parent_role_id, ruling)
        VALUES ('" . WS . "',{$rid},'PRIMARY'," . $esc($WHY) . ",NULL,
        'دورُ المراجعةِ يقود مساحتَه — PRIMARY واحدةٌ لا تتكرّر')");
    $log('③ الربطُ PRIMARY أُنشئ');
}

/* ═══ ④ مجموعةٌ لكلِّ إدارة ═══════════════════════════════════════════════ */
$depts = array();
foreach ($rows as $x) { $depts[$x['owner_dept']] = true; }
$depts = array_keys($depts);
sort($depts);
$gid = array(); $sort = 0;
foreach ($depts as $d) {
    $sort++;
    $key = mb_substr($d, 0, 140);
    $have = $one("SELECT id FROM nav_lifecycle_groups
                   WHERE workspace_id='" . WS . "' AND group_key=" . $esc($key));
    if (!$have) {
        $conn->query("INSERT INTO nav_lifecycle_groups (workspace_id, group_key, label_ar, sort_no, source_ref, active)
            VALUES ('" . WS . "'," . $esc($key) . "," . $esc($d) . ",{$sort}," . $esc($WHY) . ",1)");
        $have = $conn->insert_id;
    } else { $conn->query("UPDATE nav_lifecycle_groups SET sort_no={$sort}, active=1 WHERE id={$have}"); }
    $gid[$d] = (int) $have;
}
$log('④ مجموعاتٌ (إدارات) = ' . count($gid));

/* ═══ ⑤ المواضعُ وبنودُ التفويض ═══════════════════════════════════════════ */
$lg = $one("SELECT id FROM link_groups WHERE owner_role_id={$rid} AND name='الشاشات غير المستعملة'");
if (!$lg) {
    $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, is_active)
                  VALUES ('الشاشات غير المستعملة',NULL,{$rid},'fa fa-folder-open',100,1)");
    $lg = $conn->insert_id;
}
$cW = $cN = $cR = 0; $ord = array();
foreach ($rows as $x) {
    $d = $x['owner_dept']; $ord[$d] = (isset($ord[$d]) ? $ord[$d] : 0) + 1;
    $nrm = $x['route_norm']; $ttl = mb_substr($x['title_ar'], 0, 180);

    if (!$one("SELECT placement_id FROM nav_workspace_placements
                WHERE workspace_id='" . WS . "' AND route=" . $esc($nrm))) {
        $pid = 'WP-' . strtoupper(substr(md5(WS . '|' . $nrm), 0, 16));
        if (!$conn->query("INSERT INTO nav_workspace_placements
            (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no, route,
             canonical_label, governing_source, source_ref, reason_code, effective_from,
             effective_to, status, version, created_by, approved_by, legacy_ref)
            VALUES (" . $esc($pid) . ",NULL,'" . WS . "'," . $gid[$d] . ",'PRIMARY'," . $ord[$d] . ","
            . $esc($nrm) . "," . $esc($ttl) . ",'مراجعةُ يتيمٍ — gov_orphan_screens',"
            . $esc($WHY) . ",'ORPHAN_REVIEW',CURDATE(),NULL,'ACTIVE',1,
            'database/migrations/2028_06_15_orphan_review_workspace.php',NULL,NULL)")) {
            $die('موضعٌ فشل: ' . $conn->error);
        }
        $cW++;
    }
    if (!$one("SELECT id FROM nav_items WHERE role_id={$rid} AND route=" . $esc($x['route']))) {
        $conn->query("INSERT INTO nav_items
            (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
             counter_source, permission_code, active, is_quick, created_at, updated_at)
            VALUES ({$rid},'DAILY'," . $lg . ","
            . ($x['module_id'] === null ? 'NULL' : (int) $x['module_id']) . "," . $esc($ttl) . ","
            . $esc($x['route']) . ",'fa fa-file-lines'," . ($ord[$d] * 10) . ",NULL,"
            . ($x['module_code'] === null ? 'NULL' : $esc($x['module_code'])) . ",1,0,NOW(),NOW())");
        $cN++;
    }
    if ($x['module_id'] !== null) {
        $m = (int) $x['module_id'];
        if (!$one("SELECT id FROM role_permissions WHERE role_id={$rid} AND module_id={$m}")) {
            $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                          VALUES ({$rid},{$m},1,0,0,0)");
            $cR++;
        }
    }
}
/* ⛔ **والمزامنةُ تنزع كما تضيف**: شاشةٌ خرجت من السجلِّ (‏وُصِلت أو صُنِّفت
     مُعالِجًا لا شاشةً) يجب أن يُطفأ موضعُها — وإلّا بقيت المراجعةُ تعرض
     ما لم يعد يتيمًا، وهو **أخضرُ كاذبٌ يُنقص العملَ ويوهم بإنجازه**. */
$live = array();
foreach ($rows as $x) { $live[$x['route_norm']] = 1; }
$stale = 0;
$q = $conn->query("SELECT placement_id, route FROM nav_workspace_placements
                    WHERE workspace_id='" . WS . "' AND status='ACTIVE'");
$kill = array();
while ($q && ($x = $q->fetch_assoc())) { if (!isset($live[$x['route']])) { $kill[] = $x['placement_id']; } }
foreach ($kill as $pid) {
    $conn->query("UPDATE nav_workspace_placements SET status='RETIRED', effective_to=CURDATE()
                   WHERE placement_id=" . $esc($pid));
    $stale++;
}
$log("⑤ مواضع={$cW} · بنودُ تفويض={$cN} · منح={$cR} · مواضعُ متقادمةٌ أُطفئت={$stale}");
/* ⛔ **وبنودُ `nav_items` لا تُفعَّل**: التصريحُ للمغطَّى بقالبٍ يأتي من
     `gov_profile_items` حصرًا، وتفعيلُها هنا **يرفع دَينَ `RP-02`** بـ83
     مسارًا حيًّا بلا إدارةٍ مالكة (مقيسٌ: 82 ⇒ 165). فتُكتب أثرًا وتبقى مطفأة. */
$conn->query("UPDATE nav_items SET active=0 WHERE role_id={$rid} AND active=1");

/* ═══ ⑥ القالب — مسودّةٌ ثمَّ بنودٌ ثمَّ اعتماد ═══════════════════════════ */
$pid = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code='" . CODE . "'");
if (!$pid) {
    $r = P::createProfile($conn, array(
        'profile_code' => CODE, 'title_ar' => 'مراجعة الشاشات غير المستعملة',
        'grade' => 'G3', 'dept_code' => 'مراجعة', 'data_scope' => 'الكل',
        'fixed_rule' => 'قالب مراجعة مؤقت — رؤية فقط بلا اضافة ولا تعديل',
    ), $WHY, APPROVER);
    if (!$r['ok']) { $die('تأليفُ القالبِ فشل: ' . $r['msg']); }
    $pid = (int) $r['id'];
    $log("⑥ القالب {$pid} · " . CODE . " مسودّةً");
}
$cI = 0;
foreach ($rows as $x) {
    if ($x['module_code'] === null) { continue; }
    $r = P::setProfileItem($conn, $pid, $x['module_code'],
        array('allow' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0),
        'شاشةٌ يتيمةٌ تحت المراجعة — رؤيةٌ فقط', APPROVER);
    if ($r['ok']) { $cI++; }
}
$log('⑥ بنودُ القالب المضافة = ' . $cI . ' · المسموحُ الآن = '
    . $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$pid} AND item_kind='screen' AND allow=1"));

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$pid}") === 'draft') {
    $r = P::approveProfile($conn, $pid, 'اعتماد قالب المراجعة — رؤية فقط لشاشات يتيمة تحت القرار', APPROVER);
    if (!$r['ok'] && $r['code'] !== 'ALREADY_APPROVED') { $die('الاعتمادُ فشل: ' . $r['msg']); }
    $log('⑥ اعتماد: ' . $r['msg']);
}

/* ═══ ⑦ الحساب ثمَّ نافذةُ الصيانةِ للتفعيلِ والمنح ═══════════════════════ */
$uid = (int) $one("SELECT id FROM users WHERE username=" . $esc(UNAME));
if (!$uid) {
    $h = password_hash(UPASS, PASSWORD_BCRYPT);
    $st = $conn->prepare("INSERT INTO users (name, username, password, role, company_id, status, is_deleted, created_at)
                          VALUES (?,?,?,?,4,'active',0,NOW())");
    $nm = 'مراجعة الشاشات غير المستعملة'; $un = UNAME;
    $st->bind_param('sssi', $nm, $un, $h, $rid);
    if (!$st->execute()) { $die('إنشاءُ الحسابِ فشل: ' . $conn->error); }
    $uid = (int) $conn->insert_id;
    $log("⑦ الحساب #{$uid} «" . UNAME . "» أُنشئ");
} else {
    $conn->query("UPDATE users SET role={$rid}, status='active', is_deleted=0 WHERE id={$uid}");
    $log("⑦ الحساب #{$uid} قائمٌ — حُدِّث دورُه");
}

foreach (array(P::FREEZE_ACTIVATION, P::FREEZE_GRANTS) as $s) {
    if ($was[$s] === 0) { continue; }
    $r = P::setFreeze($conn, $s, 0, 'نافذة صيانة لتفعيل قالب المراجعة ومنحه — تغلق فور الفراغ · ' . $WHY, APPROVER);
    if (!$r['ok']) { $die("فتحُ «{$s}» فشل: " . $r['msg']); }
}
$log('⑧ البوّابتان فُتحتا');

if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id={$pid}") !== 'active') {
    $r = P::activateProfile($conn, $pid, $WHY, APPROVER);
    if (!$r['ok']) { $die('التفعيلُ فشل: ' . $r['msg']); }
    $log('⑧ تفعيل: ' . $r['msg']);
}
if (!$one("SELECT grant_id FROM gov_authority_grants WHERE user_id={$uid} AND profile_id={$pid} AND revoked_at IS NULL")) {
    $r = P::assignProfile($conn, $uid, $pid, $WHY, APPROVER);
    if (!$r['ok']) { $die('الإسنادُ فشل: ' . $r['code'] . ' — ' . $r['msg']); }
    $log('⑧ إسناد: ' . $r['msg']);
}

$restore();

/* ═══ ⑨ تحقُّقٌ ذاتيّ ═══════════════════════════════════════════════════════ */
$pl  = (int) $one("SELECT COUNT(*) FROM nav_workspace_placements WHERE workspace_id='" . WS . "' AND status='ACTIVE'");
$gp  = (int) $one("SELECT COUNT(*) FROM nav_lifecycle_groups WHERE workspace_id='" . WS . "' AND active=1");
$itm = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$pid} AND item_kind='screen' AND allow=1");
$fz  = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1");
$fzW = count(array_filter($was));
$log("⑨ مواضع={$pl} · مجموعات={$gp} · بنودُ قالب={$itm} · مجمَّدة={$fz} (المرجع {$fzW})");
if ($pl < 1 || $gp < 1 || $fz !== $fzW) {
    file_put_contents($LOGF, $buf . "التحقُّقُ لم يطابق\n"); exit(1);
}
$buf .= "\n  الدخول: «" . UNAME . "» / " . UPASS . "\n";
file_put_contents($LOGF, $buf . "اكتملت مساحةُ المراجعة\n");
exit(0);
