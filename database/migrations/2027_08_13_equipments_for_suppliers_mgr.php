<?php
/**
 * 2027_08_13_equipments_for_suppliers_mgr.php — «سجلُّ المعدات» يُفتح لمديرِ الموردين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الطلب** — إظهارُ `Equipments/equipments.php` لمديرِ الموردين (الدور 2).
 *
 * ◆ **والقفلُ أربعةٌ لا واحد** — ومَن فتح واحدًا وأعلن الفتحَ أعلن ما لم يقع:
 *   ① `gov_profile_items` — **الحاكمُ الأولُ وأخفاها**: مستخدمو الدور 2 مغطَّون
 *      بقالبٍ نافذٍ (`SUP-G7` · state=active)، و`get_module_permissions()` تُرجع
 *      حكمَ القالبِ **حصرًا** حينئذٍ: الشاشةُ خارجَ القالبِ ⇒ `t_view = -1` ⇒ منعٌ
 *      **ولا تُقرأ `role_permissions` أصلًا**. فمنحٌ في الجدولِ القديمِ وحدَه
 *      يبقى بلا أثر.
 *   ② `role_permissions` — حارسُ الخادمِ في الشاشةِ نفسِها: `can_view=0` ⇒
 *      `ems_gov_flash_redirect(GOV-PERM-403)` إلى اللوحة.
 *   ③ `nav_items` — التبعيةُ: «الدورُ يحدد الروابطَ… والصلاحيةُ تحدد أتظهر».
 *      بلا صفٍّ **لا رابطَ** ولو صحّت الصلاحيةُ كلُّها.
 *   ④ `gov_space_appearances` — عزلُ الإدارات: صفٌّ بـ`cls='FORBIDDEN'` في
 *      مساحةِ الدورِ يُزيل الرابطَ من السايدبارِ بعدَ اجتيازِ الثلاثةِ السابقة.
 *
 * ◆ **ولماذا يُكتب الصفُّ الرابعُ وإن كان «الغيابُ ليس منعًا»** — لقطةُ المساحةِ
 *   اليومَ **كاملةٌ**: ٦٦ مسارًا من ٦٦ في سايدبارِ الدورِ 2 لها صفٌّ مُصنَّف.
 *   فمسارٌ بلا صفٍّ يصير الوحيدَ المجهولَ في لقطةٍ تُقرأ حكمًا — والقرارُ يُكتب
 *   صريحًا (`CONTEXTUAL_READ_ONLY`) لا يُترك فراغًا يملؤه غيرُه لاحقًا.
 *
 * ◆ **والمنحُ قراءةٌ لا كتابة** — كما هو حالُ الاثنَي عشرَ دورًا الذين يرونها
 *   اليوم: `can_view=1` و`add/edit/delete=0`. الشاشةُ مملوكةٌ لإدارةِ الأسطول،
 *   ومديرُ الموردين يقرؤها منظرًا مرجعيًّا لا يملكه.
 *
 * ◆ **وموضعُ الرابطِ لا يُخترع**: `nav_canonical` تحمل المسارَ `APPROVED` في
 *   مجموعةِ «سجل الأصول»، و`printUxuiCanonicalNav()` تضعه هناك لكلِّ دورٍ
 *   يملكه. و`nav_items.group_id` هنا مسارُ الاحتياطِ وحدَه — فيُختار أقربَ
 *   مجموعاتِ الدورِ معنًى: «معدات المورد ومالكوها».
 *
 * التشغيل:  php database/migrations/2027_08_13_equipments_for_suppliers_mgr.php [--revert]
 * الإثبات:  php tests/equipments_suppliers_mgr_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$REVERT = in_array('--revert', $argv, true);

/* ── الثوابتُ الحاكمة ─────────────────────────────────────────────────── */
$ROUTE   = 'Equipments/equipments.php';
$ROLE    = 2;                    /* مدير الموردين — EMS_ROLE_SUPPLIERS_MGR */
$SPACE   = 'ادارة الموردين';     /* gov_space_roles.space_ar للدور 2 */
$LABEL   = 'سجل المعدات';
$GROUP   = 3448;                 /* link_groups: «معدات المورد ومالكوها» (مسارُ الاحتياط) */
$SORT    = 20;
$SEEDED  = 'owner:2026-08-20:eq_to_sup_mgr';
$BASIS   = 'قرارُ المالك 2026-08-20: منظرٌ مرجعيٌّ للقراءةِ يحتاجه مديرُ الموردين — الملكيةُ تبقى لإدارةِ الأسطول';

/* ── الموديلُ يُحلُّ من السجلِّ لا يُخمَّن (المطابقةُ الدقيقةُ على `code`) ── */
$st = $conn->prepare("SELECT id, name FROM modules WHERE code = ? LIMIT 1");
$st->bind_param('s', $ROUTE); $st->execute();
$mod = $st->get_result()->fetch_assoc(); $st->close();
if (!$mod) { exit("✗ لا صفَّ في `modules` للمسار {$ROUTE} — أُوقفت الهجرة\n"); }
$MODID = (int) $mod['id'];

/* ── القوالبُ النافذةُ لمستخدمي الدور 2 — تُقاس ولا تُكتب رقمًا مجمَّدًا ── */
$PROFILES = array();
$r = $conn->query("SELECT DISTINCT p.profile_id, p.profile_code
                     FROM gov_authority_grants g
                     JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                     JOIN users u ON u.id = g.user_id
                    WHERE u.role = {$ROLE} AND g.revoked_at IS NULL
                      AND (g.valid_to IS NULL OR g.valid_to > NOW())");
while ($r && ($x = $r->fetch_assoc())) { $PROFILES[(int) $x['profile_id']] = $x['profile_code']; }

echo ($REVERT ? "◆ عكسُ فتحِ «سجل المعدات» لمديرِ الموردين\n" : "◆ فتحُ «سجل المعدات» لمديرِ الموردين\n");
echo "  الموديل: #{$MODID} «{$mod['name']}» · المسار: {$ROUTE}\n";
echo "  القوالبُ النافذةُ للدور {$ROLE}: " . (empty($PROFILES) ? 'لا شيء' : implode(' · ', $PROFILES)) . "\n\n";

$did = array(); $skip = array();

/* ══ ① قالبُ الحوكمة — الحاكمُ الأول ══════════════════════════════════════ */
foreach ($PROFILES as $pid => $pcode) {
    $ex = $conn->prepare("SELECT item_id FROM gov_profile_items
                           WHERE profile_id = ? AND item_kind = 'screen' AND item_ref = ? LIMIT 1");
    $ex->bind_param('is', $pid, $ROUTE); $ex->execute();
    $row = $ex->get_result()->fetch_assoc(); $ex->close();

    if ($REVERT) {
        if (!$row) { $skip[] = "① القالب {$pcode}: لا صفَّ يُحذف"; continue; }
        $d = $conn->prepare("DELETE FROM gov_profile_items WHERE item_id = ? AND seeded_from = ?");
        $d->bind_param('is', $row['item_id'], $SEEDED);
        $d->execute();
        $n = $d->affected_rows; $d->close();
        if ($n > 0) { $did[] = "① القالب {$pcode}: حُذف بندُ الشاشة"; }
        else { $skip[] = "① القالب {$pcode}: الصفُّ ليس من بذرِ هذه الهجرة — تُرك"; }
        continue;
    }
    if ($row) { $skip[] = "① القالب {$pcode}: البندُ موجودٌ سلفًا"; continue; }
    $i = $conn->prepare("INSERT INTO gov_profile_items
            (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
            VALUES (0, ?, 'screen', ?, 1, 0, 0, 0, ?)");
    $i->bind_param('iss', $pid, $ROUTE, $SEEDED);
    if (!$i->execute()) { echo "  ✗ ① القالب {$pcode}: {$i->error}\n"; }
    else { $did[] = "① القالب {$pcode} (#{$pid}): allow=1 قراءةً"; }
    $i->close();
}

/* ══ ② صلاحيةُ الدور — حارسُ الشاشة ══════════════════════════════════════ */
$ex = $conn->query("SELECT id, can_view FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID} LIMIT 1");
$rp = $ex ? $ex->fetch_assoc() : null;
if ($REVERT) {
    if ($rp) {
        $conn->query("DELETE FROM role_permissions WHERE id=" . (int) $rp['id']);
        $did[] = '② role_permissions: حُذف صفُّ الدور 2';
    } else { $skip[] = '② role_permissions: لا صفَّ يُحذف'; }
} elseif ($rp) {
    if ((int) $rp['can_view'] === 1) { $skip[] = '② role_permissions: can_view=1 سلفًا'; }
    else {
        $conn->query("UPDATE role_permissions SET can_view=1 WHERE id=" . (int) $rp['id']);
        $did[] = '② role_permissions: رُفعت can_view إلى 1';
    }
} else {
    $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                  VALUES ({$ROLE}, {$MODID}, 1, 0, 0, 0)");
    $did[] = '② role_permissions: أُدرج can_view=1 · add/edit/delete=0';
}

/* ══ ③ صفُّ التبعيةِ في السايدبار ════════════════════════════════════════ */
$ex = $conn->prepare("SELECT id, active FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1");
$ex->bind_param('is', $ROLE, $ROUTE); $ex->execute();
$nav = $ex->get_result()->fetch_assoc(); $ex->close();
if ($REVERT) {
    if ($nav) {
        $conn->query("DELETE FROM nav_items WHERE id=" . (int) $nav['id']);
        $did[] = '③ nav_items: حُذف صفُّ الرابط';
    } else { $skip[] = '③ nav_items: لا صفَّ يُحذف'; }
} elseif ($nav) {
    if ((int) $nav['active'] === 1) { $skip[] = '③ nav_items: الصفُّ نشِطٌ سلفًا'; }
    else {
        $conn->query("UPDATE nav_items SET active=1 WHERE id=" . (int) $nav['id']);
        $did[] = '③ nav_items: أُنشِط الصفُّ القائم';
    }
} else {
    $i = $conn->prepare("INSERT INTO nav_items
            (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, counter_source, permission_code, active)
            VALUES (?, 'DAILY', ?, ?, ?, ?, 'fa fa-circle-dot', ?, '', ?, 1)");
    $i->bind_param('iiissis', $ROLE, $GROUP, $MODID, $LABEL, $ROUTE, $SORT, $ROUTE);
    if (!$i->execute()) { echo "  ✗ ③ nav_items: {$i->error}\n"; }
    else { $did[] = '③ nav_items: أُدرج رابطُ «' . $LABEL . '» (باب DAILY · مجموعة #' . $GROUP . ')'; }
    $i->close();
}

/* ══ ④ لقطةُ عزلِ الإدارات — القرارُ يُكتب صريحًا ═══════════════════════ */
$ex = $conn->prepare("SELECT id, cls FROM gov_space_appearances
                       WHERE space_ar = ? AND LOWER(route) = LOWER(?) LIMIT 1");
$ex->bind_param('ss', $SPACE, $ROUTE); $ex->execute();
$app = $ex->get_result()->fetch_assoc(); $ex->close();
if ($REVERT) {
    if ($app) {
        $conn->query("DELETE FROM gov_space_appearances WHERE id=" . (int) $app['id']);
        $did[] = '④ gov_space_appearances: حُذف صفُّ المساحة';
    } else { $skip[] = '④ gov_space_appearances: لا صفَّ يُحذف'; }
} elseif ($app) {
    $skip[] = "④ gov_space_appearances: صفٌّ موجودٌ سلفًا (cls={$app['cls']})";
} else {
    /* المعرِّفُ ليس auto_increment في هذا الجدول — يُحسب ولا يُترك للقاعدة */
    $nid = 1 + (int) $conn->query("SELECT COALESCE(MAX(id),0) m FROM gov_space_appearances")->fetch_assoc()['m'];
    $own = 'إدارة الأسطول';
    $tab = 'سجل الأصول';
    $note = 'قرارُ المالك: منظرٌ مرجعيٌّ للمعداتِ يحتاجه مديرُ الموردين لإكمالِ عملِه';
    $i = $conn->prepare("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields)
            VALUES (?, ?, 'DEPARTMENT', ?, ?, ?, ?, 'BUSINESS_DEPARTMENT',
                    'CONTEXTUAL_READ_ONLY', 'VALID', 'CONFIRMED', ?, 0,
                    'CONTEXTUAL_READ_ONLY', 'VALID', 'CONFIRMED', ?, 0, '')");
    $i->bind_param('isssssss', $nid, $SPACE, $tab, $LABEL, $ROUTE, $own, $note, $BASIS);
    if (!$i->execute()) { echo "  ✗ ④ gov_space_appearances: {$i->error}\n"; }
    else { $did[] = "④ gov_space_appearances: صفٌّ #{$nid} — CONTEXTUAL_READ_ONLY في «{$SPACE}»"; }
    $i->close();
}

/* عدَّادُ المساحاتِ عددٌ مشتقٌّ — يُوحَّد على الواقعِ بعدَ الكتابة، لا يُترك متناقضًا */
$cnt = (int) $conn->query("SELECT COUNT(*) c FROM gov_space_appearances
                            WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($ROUTE) . "')")->fetch_assoc()['c'];
$conn->query("UPDATE gov_space_appearances SET spaces_count={$cnt}
               WHERE LOWER(route)=LOWER('" . $conn->real_escape_string($ROUTE) . "')");

/* ══ الإثباتُ بعدَ الكتابة ═══════════════════════════════════════════════ */
echo "  ▸ ما وقع:\n";
foreach ($did as $d) { echo "     ✔ {$d}\n"; }
if (empty($did)) { echo "     — لا شيء\n"; }
if ($skip) { echo "  ▸ ما تُرك:\n"; foreach ($skip as $s) { echo "     · {$s}\n"; } }

echo "\n  ▸ الحالةُ الآن (قياسٌ لا ادّعاء):\n";
$q = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? (int) $x[0] : 0; };
$gp = 0;
foreach ($PROFILES as $pid => $pc) {
    $gp += $q("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id={$pid}
                AND item_kind='screen' AND item_ref='{$ROUTE}' AND allow=1");
}
echo "     ① قالبُ الحوكمة   : {$gp} من " . count($PROFILES) . " قالبًا نافذًا يشمل الشاشة\n";
echo "     ② can_view        : " . $q("SELECT COALESCE(can_view,0) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}") . "\n";
echo "     ②' كتابةٌ ممنوحة  : " . $q("SELECT COALESCE(can_add,0)+COALESCE(can_edit,0)+COALESCE(can_delete,0) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}") . " (المطلوب 0)\n";
echo "     ③ nav_items نشِط  : " . $q("SELECT COUNT(*) FROM nav_items WHERE role_id={$ROLE} AND route='{$ROUTE}' AND active=1") . "\n";
echo "     ④ FORBIDDEN هنا   : " . $q("SELECT COUNT(*) FROM gov_space_appearances WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}') AND cls='FORBIDDEN'") . " (المطلوب 0)\n";
echo "     ④' صفُّ المساحة   : " . $q("SELECT COUNT(*) FROM gov_space_appearances WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}')") . " · مساحاتُ المسار: {$cnt}\n";

echo "\n  ▸ الإثباتُ المُلزِم: php tests/equipments_suppliers_mgr_http_proof.php\n";
