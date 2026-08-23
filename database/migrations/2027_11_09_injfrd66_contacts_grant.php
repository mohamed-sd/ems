<?php
/**
 * 2027_11_09_injfrd66_contacts_grant.php
 *   SAL-02 · SUP-02 — فتحُ تبويبَي جهاتِ الاتصال **بلا بندِ تنقّل**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **والقفلُ أربعةٌ لا واحد** — ومَن فتح واحدًا وأعلن الفتحَ أعلن ما لم يقع:
 *   ① `gov_profile_items` — الحاكمُ الأول: مستخدمٌ تحتَ قالبٍ نافذٍ يُحكَم
 *      بالقالبِ **حصرًا**، ولا تُقرأ `role_permissions` أصلًا.
 *   ② `role_permissions` — حارسُ الشاشةِ نفسِها.
 *   ③ `nav_items` — **يُترك مغلقًا عمدًا**.
 *   ④ `gov_space_appearances` — عزلُ الإدارات.
 *
 * ◆ **والقفلُ الثالثُ يُترك مغلقًا بنصِّ المتطلبِ لا بسهو**: `SAL-02` معيارُه
 *   «**لا بندَ تنقّلٍ لجهات الاتصال**» و`SUP-02` «**صفر بندِ تنقّلٍ**». فصفٌّ في
 *   `nav_items` **يُحمِّر المتطلبَ الذي يخدمه هذا المنح**. والبلوغُ من شريطِ
 *   الملفِّ الأمّ — وهو مقيسٌ في `injfrd66_w5_reach_gate`.
 *
 * ◆ **والقراءةُ لمن يقرأ الملفَّ الأمَّ والكتابةُ لمالكِ الإدارةِ وحدَه**: التبويبُ
 *   جزءٌ من الملفّ، فمن يفتحه يراه؛ **والكتابةُ ملكيةٌ لا اطّلاع**.
 *
 * ◆ **ولا يُخترَع صفُّ موديولٍ ثانٍ لمسارٍ له صفّ**: `Clients/clients.php` نفسُه
 *   له **صفّان** (#1 و#35) وحارسُ الصلاحيةِ يحلُّ أدناهما — وتوأمٌ ثالثٌ يُضاعف
 *   العطب. فيُفحَص الوجودُ قبلَ الإدراج.
 *
 * التشغيل:  php database/migrations/2027_11_09_injfrd66_contacts_grant.php [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REVERT = in_array('--revert', $argv, true);
$SEEDED = 'injfrd66:2026-08-23:party_contacts';

$SURFACES = array(
    array(
        'route'  => 'Clients/client_contacts.php',
        'name'   => 'جهات اتصال العميل',
        'parent' => 'Clients/clients.php',
        'owner'  => 12,                      /* المبيعات — يكتب */
        'space'  => 'ادارة المبيعات',
        'tab'    => 'العملاء والفرص',
        'screen' => 'جهات اتصال العميل',
        'dept'   => 'ادارة المبيعات',
    ),
    array(
        'route'  => 'Suppliers/supplier_contacts.php',
        'name'   => 'جهات الاتصال والمفوضون',
        'parent' => 'Suppliers/suppliers.php',
        'owner'  => 2,                       /* إدارة الموردين — تكتب */
        'space'  => 'ادارة الموردين',
        'tab'    => 'الموردون والتعاقد',
        'screen' => 'جهات الاتصال والمفوضون',
        'dept'   => 'ادارة الموردين',
    ),
);

echo "\n══ INJ-FRD-01 · SAL-02 · SUP-02 — فتحُ التبويبَين ══\n";
$did = array(); $skip = array(); $warn = array();

foreach ($SURFACES as $S) {
    printf("\n  ── %s\n", $S['route']);

    /* ── الموديل ─────────────────────────────────────────────────────── */
    $st = $conn->prepare("SELECT id FROM modules WHERE code = ? ORDER BY id ASC LIMIT 1");
    $st->bind_param('s', $S['route']); $st->execute();
    $mod = $st->get_result()->fetch_assoc(); $st->close();

    if ($REVERT) {
        if (!$mod) { $skip[] = $S['route'] . ' — لا موديلَ يُحذف'; continue; }
        $mid = (int) $mod['id'];
        $conn->query("DELETE FROM role_permissions WHERE module_id = {$mid}");
        $d = $conn->prepare("DELETE FROM gov_profile_items WHERE item_ref = ? AND seeded_from = ?");
        $d->bind_param('ss', $S['route'], $SEEDED); $d->execute(); $d->close();
        $d = $conn->prepare("DELETE FROM gov_space_appearances WHERE route = ? AND basis = ?");
        $d->bind_param('ss', $S['route'], $SEEDED); $d->execute(); $d->close();
        $conn->query("DELETE FROM modules WHERE id = {$mid}");
        $did[] = $S['route'] . ' — أُزيلت الأقفالُ الثلاثةُ والموديل';
        continue;
    }

    if ($mod) { $MID = (int) $mod['id']; $skip[] = 'الموديل قائمٌ سلفًا #' . $MID; }
    else {
        $pm = $conn->prepare("SELECT group_id FROM modules WHERE code = ? ORDER BY id ASC LIMIT 1");
        $pm->bind_param('s', $S['parent']); $pm->execute();
        $prow = $pm->get_result()->fetch_assoc(); $pm->close();
        $gid = $prow ? $prow['group_id'] : null;
        $i = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon)
                             VALUES (?, ?, ?, ?, 0, 0, 'fa fa-address-book')");
        $i->bind_param('ssii', $S['name'], $S['route'], $S['owner'], $gid);
        if (!$i->execute()) { exit("  ✘ تعذّر إدراجُ الموديل: {$i->error}\n"); }
        $MID = (int) $conn->insert_id; $i->close();
        $did[] = 'الموديل #' . $MID . ' — ' . $S['name'];
    }
    printf("     الموديل: #%d\n", $MID);

    /* ── ② الصلاحية: قراءةٌ لمن يقرأ الأمَّ · وكتابةٌ للمالك ──────────── */
    $viewers = array();
    $q = $conn->query("SELECT DISTINCT rp.role_id FROM role_permissions rp
                         JOIN modules m ON m.id = rp.module_id
                        WHERE m.code = '" . $conn->real_escape_string($S['parent']) . "'
                          AND rp.can_view = 1");
    while ($q && ($x = $q->fetch_assoc())) { $viewers[] = (int) $x['role_id']; }
    if (!in_array($S['owner'], $viewers, true)) { $viewers[] = $S['owner']; }
    sort($viewers);
    $added = 0;
    foreach ($viewers as $rid) {
        $w = ($rid === $S['owner']) ? 1 : 0;
        $e = $conn->query("SELECT id FROM role_permissions WHERE role_id={$rid} AND module_id={$MID} LIMIT 1");
        if ($e && $e->fetch_assoc()) { continue; }
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ({$rid}, {$MID}, 1, {$w}, {$w}, {$w})");
        $added++;
    }
    printf("     ② صلاحية: %d دورًا يقرأ · والكتابةُ للدور %d وحدَه (أُدرج %d)\n",
        count($viewers), $S['owner'], $added);

    /* ── ① قوالبُ الحوكمةِ النافذةُ لمن مُنح ───────────────────────────── */
    $in = implode(',', array_map('intval', $viewers));
    $profiles = array();
    $q = $conn->query("SELECT DISTINCT p.profile_id, p.profile_code
                         FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                         JOIN users u ON u.id = g.user_id
                        WHERE COALESCE(u.role_id, NULLIF(CAST(u.role AS UNSIGNED),0)) IN ({$in})
                          AND g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    while ($q && ($x = $q->fetch_assoc())) { $profiles[(int) $x['profile_id']] = $x['profile_code']; }
    /* ◆ **والقالبُ يُلغي `role_permissions` كليًّا** — فمنحُ الكتابةِ في ② وحدَه
         **بلا أثر** على مستخدمٍ تحتَ قالبٍ نافذ: يقرأ ولا يكتب، والسببُ
         لا يظهر في أيِّ رسالة. فتُقاس قوالبُ **المالكِ** خاصةً وتُفتح لها
         الكتابةُ كما فُتحت في ②. (قِيس: النموذجُ اختفى لدورِ المبيعاتِ
         وهو المالكُ لأنَّ بندَ قالبِه كان `can_add = 0`.) */
    $ownerProfiles = array();
    $q = $conn->query("SELECT DISTINCT g.profile_id
                         FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                         JOIN users u ON u.id = g.user_id
                        WHERE COALESCE(u.role_id, NULLIF(CAST(u.role AS UNSIGNED),0)) = " . (int) $S['owner'] . "
                          AND g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    while ($q && ($x = $q->fetch_assoc())) { $ownerProfiles[(int) $x['profile_id']] = 1; }

    $pAdd = 0;
    foreach ($profiles as $pid => $pcode) {
        $e = $conn->prepare("SELECT item_id FROM gov_profile_items
                              WHERE profile_id = ? AND item_kind = 'screen' AND item_ref = ? LIMIT 1");
        $e->bind_param('is', $pid, $S['route']); $e->execute();
        $has = $e->get_result()->fetch_assoc(); $e->close();
        if ($has) { continue; }
        $w = isset($ownerProfiles[$pid]) ? 1 : 0;
        $i = $conn->prepare("INSERT INTO gov_profile_items
                (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                VALUES (0, ?, 'screen', ?, 1, ?, ?, ?, ?)");
        $i->bind_param('isiiis', $pid, $S['route'], $w, $w, $w, $SEEDED);
        if ($i->execute()) { $pAdd++; }
        $i->close();
    }
    printf("     ① قوالبُ حوكمةٍ نافذة: %d · أُضيف بندٌ في %d\n", count($profiles), $pAdd);

    /* ── ③ التنقّل: **يُترك مغلقًا عمدًا** ───────────────────────────── */
    $e = $conn->prepare("SELECT COUNT(*) FROM nav_items WHERE route = ?");
    $e->bind_param('s', $S['route']); $e->execute();
    $navN = (int) $e->get_result()->fetch_row()[0]; $e->close();
    if ($navN > 0) {
        $warn[] = $S['route'] . ' — له ' . $navN . ' صفَّ تنقّلٍ وهو ما يمنعه المعيار';
        printf("     ③ ✘ صفوفُ تنقّلٍ: %d — **والمعيارُ يمنعها**\n", $navN);
    } else {
        printf("     ③ ✔ صفرُ صفِّ تنقّلٍ — **مغلقٌ عمدًا بنصِّ المتطلب**\n");
    }

    /* ── ④ لقطةُ المساحة: تُكتب صريحةً لا تُترك فراغًا ────────────────── */
    $e = $conn->prepare("SELECT id FROM gov_space_appearances WHERE space_ar = ? AND route = ? LIMIT 1");
    $e->bind_param('ss', $S['space'], $S['route']); $e->execute();
    $ap = $e->get_result()->fetch_assoc(); $e->close();
    if ($ap) { printf("     ④ لقطةُ المساحةِ قائمةٌ سلفًا\n"); }
    else {
        /* `id` ليس تلقائيًّا في هذا الجدول — يُحسب */
        $nid = 1 + (int) ($conn->query("SELECT COALESCE(MAX(id),0) FROM gov_space_appearances")->fetch_row()[0]);
        $i = $conn->prepare("INSERT INTO gov_space_appearances
                (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
                 cls, ownership, decision, basis, rule_step, spaces_count)
                VALUES (?, ?, 'إدارة', ?, ?, ?, ?, 'إدارة', 'OWNED', 'VALID', 'KEEP', ?, 1, 1)");
        $i->bind_param('issssss', $nid, $S['space'], $S['tab'], $S['screen'], $S['route'],
            $S['dept'], $SEEDED);
        if ($i->execute()) { printf("     ④ لقطةُ المساحةِ #%d — OWNED في «%s»\n", $nid, $S['space']); }
        else { printf("     ④ ✘ %s\n", $i->error); }
        $i->close();
    }
}

echo "\n  ── الحصيلة\n";
foreach ($did as $d)  { echo "     ✔ {$d}\n"; }
foreach ($skip as $s) { echo "     ○ {$s}\n"; }
foreach ($warn as $w) { echo "     ✘ {$w}\n"; }
echo "\n  ◆ والقفلُ الثالثُ مغلقٌ عمدًا: «تبويبٌ في الملفِّ لا شاشة» — وصفٌّ في\n";
echo "    `nav_items` يُحمِّر المتطلبَ الذي يخدمه هذا المنح.\n\n";
exit(empty($warn) ? 0 : 1);
