<?php
/**
 * 2027_08_21_finreq_merge_request_screens.php — دمجُ شاشتَي الطلبِ الماليِّ في واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك (2026-08-21)**: «صفحةُ إضافةِ الطلبِ الماليِّ وصفحةُ عرضِ
 *   الطلباتِ — ألا يمكن دمجُهما في صفحةٍ واحدةٍ لتقليلِ عددِ الصفحاتِ التي لا
 *   حاجةَ لها، بحيث تكون مثلَ صفحةِ العملاءِ مع بطاقاتِ إحصاءٍ وفلاترِ بحث».
 *
 * ◆ **الشاشةُ الباقيةُ `FinRequests/request_form.php`** — والقرارُ مقيسٌ لا
 *   مذوَّق: `can_add` مسجَّلةٌ على وحدتِها لأربعةَ عشرَ دورًا (بهويّاتٍ حقيقية)،
 *   وعلى وحدةِ `my_requests.php` **صفرٌ لكلِّ الأدوارِ الخمسةِ والثلاثين**.
 *   فلو بقيت الأخرى لَتعطَّل الإنشاءُ حتى تُهاجَر الصلاحياتُ كلُّها — والدمجُ
 *   لا يُشترى بإسقاطِ حارس. و`my_requests.php` صارت مُحوِّلًا بعدّادِ نقرات.
 *
 * ◆ **وما تفعله هذه الهجرةُ خمسةُ أشياءَ لا أقلّ** — فترْكُ أيِّها يُنتج نصفَ
 *   تطبيقٍ يبدو مكتملًا:
 *   ① **الصلاحية**: أربعةُ أدوارٍ (9 التنفيذية · 25 المستودع · 26 التمويل ·
 *      27 القوى) لها `can_view` على المدموجةِ **ولا صفَّ لها** على الباقية —
 *      فبلا منحٍ يفقدون «طلباتي» صامتًا. يُمنح `can_view` وحدَه (لا إضافةَ ولا
 *      تحريرَ ولا حذف) — قدْرَ ما كان لهم حرفًا.
 *   ② **قوالبُ الحوكمة**: `gov_profile_items` **تُلغي `role_permissions` كليًّا**
 *      لمن يغطّيه قالبٌ نافذ. فقالبٌ يمنح المدموجةَ ولا يمنح الباقيةَ = منعٌ
 *      بالقالب. يُنسَخ البندُ بالوسمِ `seeded_from` فيُعرَف عند الرجوع.
 *   ③ **التبعية** `nav_items`: القيدُ `UNIQUE(role_id, route)` يمنع تحويلَ صفٍّ
 *      إلى مسارٍ للدورِ صفٌّ عليه سلفًا — فمن له الصفّان يُطفأ أحدُهما، ومن له
 *      المدموجُ وحدَه يُحوَّل صفُّه. (والتوأمُ الراكدُ هنا خطرٌ حقيقيٌّ لا نظري.)
 *   ④ **السجل** `nav_canonical_current`: مصدرٌ ثانٍ للتنقُّل — وتحويلُ الأولِ
 *      وحدَه يصطنع توأمَ القديمِ بأيقونةِ `fa fa-link`. مفتاحُه `(route, role_id)`
 *      فيُحذف صفُّ المدموجةِ لمن له الصفّان ويُحوَّل لمن له واحد.
 *   ⑤ **التصنيفُ والتحويل**: `nav_route_group` يُنقل إلى `MINE` (طلباتي مساحةٌ
 *      شخصيةٌ لكلِّ دورٍ لا بابٌ من أبوابِ المالية — والوسمُ في `nav_groups.php`
 *      نُقل معه)، و`nav_redirects` يُسجَّل فيه المسارُ المتقاعدُ بعدّادِ نقراتِه.
 *
 * ◆ **ولا يُفتح قفلٌ جديد**: كلُّ منحٍ هنا **مرآةُ منحٍ قائمٍ** على الشاشةِ
 *   المدموجة — لا دورَ يرى ما لم يكن يراه، ولا فعلَ يُضاف.
 *
 * ◆ **الرجوعُ يقرأ لقطةً لا يخمّن**: كلُّ ما تُغيّره الهجرةُ يُلتقط قبلَ
 *   التغييرِ في `storage/backups/finreq_merge_20260821/state.json`، و`--revert` يستعيد
 *   منها حرفًا. فلا يُحذف صفٌّ لم تُنشئه ولا يُنزع منحٌ لم تمنحه.
 *
 * التشغيل:  php database/migrations/2027_08_21_finreq_merge_request_screens.php
 * الرجوع :  php database/migrations/2027_08_21_finreq_merge_request_screens.php --revert
 * ثم     :  php database/migrate.php dump-schema
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

/* ── ثوابتُ الجولة ─────────────────────────────────────────────────────── */
$KEEP      = 'FinRequests/request_form.php';   // الشاشةُ الباقية (الموحَّدة)
$GONE      = 'FinRequests/my_requests.php';    // الشاشةُ المدموجة (صارت مُحوِّلًا)
$KEEP_KEY  = mb_strtolower($KEEP);
$GONE_KEY  = mb_strtolower($GONE);
$LABEL     = 'طلباتي المالية';
$ICON      = 'fa fa-paper-plane';
$SEED_TAG  = 'finreq_merge_20260821';
$GROUP_NEW = 'MINE';
/* اللقطةُ تحت `storage/backups/` **بقصد**: هي حالُ **هذه** القاعدةِ قبلَ الجولةِ
   (مُعرِّفاتُ صفوفٍ وأسماءٌ ومنحٌ)، والرجوعُ يكتبها بـ`WHERE id = …`. فلو التزمت
   في المستودعِ لَحملها فرعٌ إلى بيئةٍ أخرى، ولَكتب `--revert` هناك أرقامَ صفوفٍ
   لا تخصُّها. والمجلَّدُ مستثنًى في `.gitignore` فلا تُلتقط بـ`git add -A`. */
$STATE_DIR = $ROOT . '/storage/backups/finreq_merge_20260821';
$STATE     = $STATE_DIR . '/state.json';

$revert = in_array('--revert', $argv, true);

/* ── الوحدتان بالرمزِ لا برقمٍ محفوظ (الأرقامُ تختلف بين النسخ) ─────────── */
$modId = function (mysqli $c, $code) {
    // أدنى id عند التكرار — سلوكُ حارسِ الصلاحياتِ نفسِه، فلا يفترق الفحصُ عن الحكم
    $st = $c->prepare("SELECT MIN(id) FROM modules WHERE code = ?");
    $st->bind_param('s', $code); $st->execute();
    $st->bind_result($id); $st->fetch(); $st->close();
    return $id === null ? 0 : (int) $id;
};
$RF = $modId($conn, $KEEP);
$MR = $modId($conn, $GONE);
if ($RF === 0) { exit("✘ لا وحدةَ مسجَّلةٌ للمسار «{$KEEP}» — أُوقفت الهجرة\n"); }
if ($MR === 0) { exit("✘ لا وحدةَ مسجَّلةٌ للمسار «{$GONE}» — أُوقفت الهجرة\n"); }

$rows = function (mysqli $c, $sql) {
    $out = array(); $r = $c->query($sql);
    if (!$r) { exit("✘ استعلامٌ فشل: {$c->error}\n{$sql}\n"); }
    while ($x = $r->fetch_assoc()) { $out[] = $x; }
    return $out;
};
$run = function (mysqli $c, $sql) {
    if (!$c->query($sql)) { exit("✘ تنفيذٌ فشل: {$c->error}\n{$sql}\n"); }
    return $c->affected_rows;
};
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "الوحدتان: الباقيةُ «{$KEEP}» (id={$RF}) ⇐ المدموجةُ «{$GONE}» (id={$MR})\n";
echo "═══════════════════════════════════════════════════════════════════\n";

/* ═══════════════════════════════════════════════════════════════════════
   الرجوع — يقرأ اللقطةَ ويستعيد حرفًا
   ═══════════════════════════════════════════════════════════════════════ */
if ($revert) {
    if (!is_file($STATE)) { exit("✘ لا لقطةَ في {$STATE} — لا رجوعَ بلا مرجع\n"); }
    $S = json_decode(file_get_contents($STATE), true);
    if (!is_array($S)) { exit("✘ اللقطةُ غيرُ مقروءة\n"); }

    foreach ($S['modules'] as $m) {
        $run($conn, "UPDATE modules SET name = '" . $esc($m['name']) . "', is_link = '" . $esc($m['is_link'])
                  . "' WHERE id = " . (int) $m['id']);
    }
    foreach ($S['perm_inserted'] as $r) {
        $run($conn, "DELETE FROM role_permissions WHERE role_id = " . (int) $r . " AND module_id = {$RF}");
    }
    foreach ($S['perm_updated'] as $r) {
        $run($conn, "UPDATE role_permissions SET can_view = " . (int) $r['can_view']
                  . " WHERE role_id = " . (int) $r['role_id'] . " AND module_id = {$RF}");
    }
    $run($conn, "DELETE FROM gov_profile_items WHERE seeded_from = '" . $esc($SEED_TAG) . "'");
    foreach ((isset($S['perm_gone']) ? $S['perm_gone'] : array()) as $r) {
        $run($conn, "INSERT IGNORE INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                     VALUES (" . (int) $r['role_id'] . ", {$MR}, " . (int) $r['can_view'] . ", " . (int) $r['can_add']
                   . ", " . (int) $r['can_edit'] . ", " . (int) $r['can_delete'] . ")");
    }
    foreach ((isset($S['gov_gone']) ? $S['gov_gone'] : array()) as $g) {
        $run($conn, "INSERT IGNORE INTO gov_profile_items
                       (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                     VALUES (" . (int) $g['company_id'] . ", " . (int) $g['profile_id'] . ", 'screen', '" . $esc($g['item_ref']) . "', "
                   . (int) $g['allow'] . ", " . (int) $g['can_add'] . ", " . (int) $g['can_edit'] . ", "
                   . (int) $g['can_delete'] . ", '" . $esc($g['seeded_from']) . "')");
    }
    foreach ($S['nav_items'] as $n) {
        $run($conn, "UPDATE nav_items SET route = '" . $esc($n['route']) . "', permission_code = '" . $esc($n['permission_code'])
                  . "', module_id = " . (int) $n['module_id'] . ", label_ar = '" . $esc($n['label_ar'])
                  . "', icon = '" . $esc($n['icon']) . "', active = " . (int) $n['active']
                  . ", updated_at = NOW() WHERE id = " . (int) $n['id']);
    }
    foreach ($S['canon_deleted'] as $c) {
        $run($conn, "INSERT IGNORE INTO nav_canonical_current (route, role_id, cur_label, cur_group, cur_order)
                     VALUES ('" . $esc($c['route']) . "', " . (int) $c['role_id'] . ", '" . $esc($c['cur_label'])
                   . "', '" . $esc($c['cur_group']) . "', " . (int) $c['cur_order'] . ")");
    }
    foreach ($S['canon_moved'] as $c) {
        $run($conn, "UPDATE nav_canonical_current SET route = '" . $esc($c['route']) . "', cur_label = '" . $esc($c['cur_label'])
                  . "' WHERE route = '" . $esc($KEEP_KEY) . "' AND role_id = " . (int) $c['role_id']);
    }
    foreach ($S['canon_relabel'] as $c) {
        $run($conn, "UPDATE nav_canonical_current SET cur_label = '" . $esc($c['cur_label']) . "'
                      WHERE route = '" . $esc($KEEP_KEY) . "' AND role_id = " . (int) $c['role_id']);
    }
    foreach ((isset($S['canonical']) ? $S['canonical'] : array()) as $c) {
        $run($conn, "UPDATE nav_canonical SET
                        canonical_ar = '" . $esc($c['canonical_ar']) . "',
                        old_names = " . ($c['old_names'] === null ? 'NULL' : "'" . $esc($c['old_names']) . "'") . ",
                        current_label = " . ($c['current_label'] === null ? 'NULL' : "'" . $esc($c['current_label']) . "'") . ",
                        status = '" . $esc($c['status']) . "',
                        decision_state = '" . $esc($c['decision_state']) . "',
                        application_state = '" . $esc($c['application_state']) . "',
                        decision_source = " . ($c['decision_source'] === null ? 'NULL' : "'" . $esc($c['decision_source']) . "'") . ",
                        decided_at = " . ($c['decided_at'] === null ? 'NULL' : "'" . $esc($c['decided_at']) . "'") . ",
                        merge_into = " . ($c['merge_into'] === null ? 'NULL' : "'" . $esc($c['merge_into']) . "'") . ",
                        retirement_status = " . ($c['retirement_status'] === null ? 'NULL' : "'" . $esc($c['retirement_status']) . "'") . "
                      WHERE route = '" . $esc($c['route']) . "'");
    }
    foreach ($S['route_group'] as $g) {
        $run($conn, "UPDATE nav_route_group SET group_code = '" . $esc($g['group_code']) . "', basis = '" . $esc($g['basis'])
                  . "' WHERE route = '" . $esc($g['route']) . "'");
    }
    if (!empty($S['redirect_inserted'])) {
        $run($conn, "DELETE FROM nav_redirects WHERE old_route = '" . $esc($GONE) . "' AND hits = 0");
    } elseif (!empty($S['redirect_prev'])) {
        $run($conn, "UPDATE nav_redirects SET new_route = '" . $esc($S['redirect_prev']['new_route'])
                  . "', active = " . (int) $S['redirect_prev']['active']
                  . " WHERE id = " . (int) $S['redirect_prev']['id']);
    }
    @unlink($STATE);
    echo "↺ رُجع بالكامل من اللقطة · ثم: php database/migrate.php dump-schema\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════
   التطبيق — تُلتقط الحالُ أوّلًا ثم تُغيَّر
   ═══════════════════════════════════════════════════════════════════════ */
$S = array('modules' => array(), 'perm_inserted' => array(), 'perm_updated' => array(),
           'nav_items' => array(), 'canon_deleted' => array(), 'canon_moved' => array(),
           'canon_relabel' => array(), 'route_group' => array(), 'redirect_inserted' => false);

/* ── ① الوحدتان: الباقيةُ تُسمّى باسمِ الرحلةِ، والمدموجةُ تُرفع من الروابط ── */
foreach ($rows($conn, "SELECT id, name, is_link FROM modules WHERE id IN ({$RF}, {$MR})") as $m) {
    $S['modules'][] = $m;
}
$run($conn, "UPDATE modules SET name = '" . $esc($LABEL) . "' WHERE id = {$RF}");
$run($conn, "UPDATE modules SET name = '" . $esc($LABEL . ' (مدموجة في الشاشة الموحّدة)')
          . "', is_link = '0' WHERE id = {$MR}");
echo "① modules: {$RF} ⇦ «{$LABEL}» · {$MR} رُفع من الروابط (is_link=0)\n";

/* ── ② الصلاحية: مرآةُ ما كان على المدموجةِ — عرضٌ فقط، ولا فعلَ يُضاف ──── */
$granted = 0;
foreach ($rows($conn, "SELECT mr.role_id, rf.id AS rf_id, rf.can_view AS rf_view
                         FROM role_permissions mr
                    LEFT JOIN role_permissions rf ON rf.role_id = mr.role_id AND rf.module_id = {$RF}
                        WHERE mr.module_id = {$MR} AND mr.can_view = 1
                     ORDER BY mr.role_id + 0") as $r) {
    $role = (int) $r['role_id'];
    if ($r['rf_id'] === null) {
        $run($conn, "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                     VALUES ({$role}, {$RF}, 1, 0, 0, 0)");
        $S['perm_inserted'][] = $role;
        echo "   ✚ الدور {$role}: مُنح can_view على الوحدة {$RF} (كان له على {$MR})\n";
        $granted++;
    } elseif ((int) $r['rf_view'] !== 1) {
        $S['perm_updated'][] = array('role_id' => $role, 'can_view' => (int) $r['rf_view']);
        $run($conn, "UPDATE role_permissions SET can_view = 1 WHERE role_id = {$role} AND module_id = {$RF}");
        echo "   ↑ الدور {$role}: رُفع can_view إلى 1 على الوحدة {$RF}\n";
        $granted++;
    }
}
echo "② role_permissions: {$granted} دورًا سُوّي (الباقون كانوا مُسوَّين سلفًا)\n";

/* ── ③ قوالبُ الحوكمة: القالبُ يُلغي `role_permissions` كليًّا لمن يغطّيه ── */
$mirrored = 0;
foreach ($rows($conn, "SELECT i.company_id, i.profile_id, i.allow, i.can_add, i.can_edit, i.can_delete
                         FROM gov_profile_items i
                        WHERE i.item_kind = 'screen' AND i.item_ref = '" . $esc($GONE) . "'
                          AND NOT EXISTS (SELECT 1 FROM gov_profile_items j
                                           WHERE j.profile_id = i.profile_id AND j.item_kind = 'screen'
                                             AND j.item_ref = '" . $esc($KEEP) . "')") as $i) {
    $run($conn, "INSERT IGNORE INTO gov_profile_items
                   (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                 VALUES (" . (int) $i['company_id'] . ", " . (int) $i['profile_id'] . ", 'screen', '" . $esc($KEEP) . "', "
               . (int) $i['allow'] . ", " . (int) $i['can_add'] . ", " . (int) $i['can_edit'] . ", "
               . (int) $i['can_delete'] . ", '" . $esc($SEED_TAG) . "')");
    $mirrored++;
}
echo "③ gov_profile_items: {$mirrored} قالبًا نُسخ فيه بندُ الشاشةِ الباقية\n";

/* ── ③-ب المنحُ يتبع الشاشةَ: ما تقاعد لا يبقى ممنوحًا ──────────────────
   قرارُ المالك 2027_02_13-① («فعّلها كلَّها — الممنوحُ يُرى») يجعل `active`
   مقبضًا إداريًّا لا حكمَ صلاحيةٍ ثانيًا، **فلا شاشةَ ممنوحةً يخفيها
   `active=0`**. ولولا هذه الخطوةُ لَخلَّف الدمجُ ستةً وعشرين دورًا لكلٍّ منها
   `can_view` على وحدةٍ صفُّها مُطفأ — تناقضٌ يرصده `tests/unified_nav_test.php`
   وهو محقٌّ فيه: الشاشةُ لم تُخفَ، **زالت**. فيُنزع منحُها من الطبقتَين معًا
   (`role_permissions` والقوالب) — ولا وصولَ يُفقَد لأن المنحَ نُسخ إلى
   الباقيةِ في ② و③ قبلَ هذه الخطوة. */
$S['perm_gone'] = $rows($conn, "SELECT role_id, can_view, can_add, can_edit, can_delete
                                  FROM role_permissions WHERE module_id = {$MR}");
$nPerm = $run($conn, "DELETE FROM role_permissions WHERE module_id = {$MR}");
$S['gov_gone'] = $rows($conn, "SELECT company_id, profile_id, item_ref, allow, can_add, can_edit, can_delete, seeded_from
                                 FROM gov_profile_items WHERE item_kind = 'screen' AND item_ref = '" . $esc($GONE) . "'");
$nGov = $run($conn, "DELETE FROM gov_profile_items WHERE item_kind = 'screen' AND item_ref = '" . $esc($GONE) . "'");
echo "③-ب منحُ المتقاعدِ نُزع: {$nPerm} صفَّ صلاحيةٍ · {$nGov} بندَ قالب\n";

/* ── ④ التبعية: من له الصفّان يُطفأ أحدُهما · ومن له المدموجُ وحدَه يُحوَّل ── */
$keepRoles = array();
foreach ($rows($conn, "SELECT role_id FROM nav_items WHERE route = '" . $esc($KEEP) . "'") as $x) {
    $keepRoles[(int) $x['role_id']] = true;
}
$off = 0; $moved = 0; $relabelled = 0;
foreach ($rows($conn, "SELECT id, role_id, route, permission_code, module_id, label_ar, icon, active
                         FROM nav_items WHERE route = '" . $esc($GONE) . "' ORDER BY role_id + 0") as $n) {
    $S['nav_items'][] = $n;
    $role = (int) $n['role_id'];
    if (isset($keepRoles[$role])) {
        // القيدُ UNIQUE(role_id, route) يمنع التحويل — والصفُّ الباقي يكفي الدور
        $run($conn, "UPDATE nav_items SET active = 0, updated_at = NOW() WHERE id = " . (int) $n['id']);
        $off++;
    } else {
        $run($conn, "UPDATE nav_items SET route = '" . $esc($KEEP) . "', permission_code = '" . $esc($KEEP)
                  . "', module_id = {$RF}, label_ar = '" . $esc($LABEL) . "', icon = '" . $esc($ICON)
                  . "', updated_at = NOW() WHERE id = " . (int) $n['id']);
        $moved++;
    }
}
foreach ($rows($conn, "SELECT id, role_id, route, permission_code, module_id, label_ar, icon, active
                         FROM nav_items WHERE route = '" . $esc($KEEP) . "' ORDER BY role_id + 0") as $n) {
    if ((int) $n['module_id'] === $RF && $n['label_ar'] === $LABEL) { continue; }  // عطالة
    $S['nav_items'][] = $n;
    $run($conn, "UPDATE nav_items SET label_ar = '" . $esc($LABEL) . "', icon = '" . $esc($ICON)
              . "', module_id = {$RF}, updated_at = NOW() WHERE id = " . (int) $n['id']);
    $relabelled++;
}
echo "④ nav_items: {$moved} صفًّا حُوِّل · {$off} أُطفئ (للدورِ صفٌّ باقٍ) · {$relabelled} أُعيدت تسميتُه\n";

/* ── ⑤ السجل: مصدرُ التنقُّلِ الثاني — وبلا تحويلِه يُصطنع توأمُ القديم ──── */
$canonKeep = array();
foreach ($rows($conn, "SELECT role_id FROM nav_canonical_current WHERE route = '" . $esc($KEEP_KEY) . "'") as $x) {
    $canonKeep[(int) $x['role_id']] = true;
}
$cDel = 0; $cMov = 0;
foreach ($rows($conn, "SELECT route, role_id, cur_label, cur_group, cur_order
                         FROM nav_canonical_current WHERE route = '" . $esc($GONE_KEY) . "' ORDER BY role_id + 0") as $c) {
    $role = (int) $c['role_id'];
    if (isset($canonKeep[$role])) {
        $S['canon_deleted'][] = $c;
        $run($conn, "DELETE FROM nav_canonical_current WHERE route = '" . $esc($GONE_KEY) . "' AND role_id = {$role}");
        $cDel++;
    } else {
        $S['canon_moved'][] = $c;
        $run($conn, "UPDATE nav_canonical_current SET route = '" . $esc($KEEP_KEY) . "', cur_label = '" . $esc($LABEL)
                  . "' WHERE route = '" . $esc($GONE_KEY) . "' AND role_id = {$role}");
        $cMov++;
    }
}
$cRel = 0;
foreach ($rows($conn, "SELECT role_id, cur_label FROM nav_canonical_current
                        WHERE route = '" . $esc($KEEP_KEY) . "' AND cur_label <> '" . $esc($LABEL) . "'") as $c) {
    $S['canon_relabel'][] = $c;
    $run($conn, "UPDATE nav_canonical_current SET cur_label = '" . $esc($LABEL) . "'
                  WHERE route = '" . $esc($KEEP_KEY) . "' AND role_id = " . (int) $c['role_id']);
    $cRel++;
}
echo "⑤ nav_canonical_current: {$cMov} حُوِّل · {$cDel} حُذف (للدورِ صفٌّ باقٍ) · {$cRel} أُعيدت تسميتُه\n";

/* ── ⑥ التصنيفُ والتحويل ─────────────────────────────────────────────── */
foreach ($rows($conn, "SELECT route, group_code, basis FROM nav_route_group
                        WHERE route IN ('" . $esc($KEEP_KEY) . "', '" . $esc($GONE_KEY) . "')") as $g) {
    $S['route_group'][] = $g;
}
$run($conn, "UPDATE nav_route_group SET group_code = '" . $esc($GROUP_NEW) . "', basis = 'PIN', updated_at = NOW()
              WHERE route = '" . $esc($KEEP_KEY) . "'");
/* سجلُّ التحويلِ قد يحمل صفًّا قديمًا لوجهةٍ أخرى (كان يقود إلى «مساحة عملي»
   قبلَ أن تصير للطلبِ الماليِّ شاشتُه الموحَّدة) — فلا يكفي `INSERT IGNORE`:
   الصفُّ الموجودُ **يُصحَّح مقصدُه** وإلا بقي السجلُّ يقول غيرَ ما يفعل الملف. */
$prev = $rows($conn, "SELECT id, new_route, active FROM nav_redirects WHERE old_route = '" . $esc($GONE) . "'");
if (!$prev) {
    $run($conn, "INSERT INTO nav_redirects (old_route, new_route, active, hits, created_at)
                 VALUES ('" . $esc($GONE) . "', '" . $esc($KEEP) . "', 1, 0, NOW())");
    $S['redirect_inserted'] = true;
    $note = 'سُجِّل المسارُ المتقاعد';
} else {
    $S['redirect_prev'] = $prev[0];
    $run($conn, "UPDATE nav_redirects SET new_route = '" . $esc($KEEP) . "', active = 1
                  WHERE old_route = '" . $esc($GONE) . "'");
    $note = 'صُحِّح مقصدُه (كان «' . $prev[0]['new_route'] . '»)';
}
echo "⑥ nav_route_group: «{$KEEP_KEY}» ⇦ {$GROUP_NEW} · nav_redirects: {$note}\n";

/* ── ⑦ المصفوفةُ المعيارية `nav_canonical` — قناةُ إعادةِ التسميةِ الوحيدة ──
   ◆ **ولا يجوز تخطّيها**: `tools/uxui_preserve_check.php --gate` في `pre-commit`
     يقارن كلَّ سايدبارٍ بالأساسِ الملتزَم، ويقبل تغيُّرَ اسمٍ **بشرطٍ واحد**:
     أن يكون الاسمُ القديمُ مسجَّلًا في `old_names` لصفٍّ حالتُه `APPROVED`.
     فبدونها يقرأ الحارسُ «ناقص: طلب مالي جديد · جديدُ الظهور: طلباتي المالية»
     ويُرسِّب — **ويمنع كلَّ التزامٍ يمسُّ .php أو .css من كلِّ جلسةٍ**، لا من
     جلسةِ الدمجِ وحدَها. (وقع فعلًا: عشرةُ أدوارٍ · 2026-08-21.)
   ◆ والمتقاعدُ يُسجَّل `MERGED` بصيغةِ سابقتِه المعتمَدة
     (`Tickets/dept_inbox.php` — دُمج تبويبًا وبقي ملفُّه مُحوِّلًا)، فالسجلُّ
     يقول ما جرى: **دمجٌ بوارثٍ مُعلَنٍ لا حذفٌ صامت**. */
$SRC = 'أمرُ المالك 2026-08-21 — دمجُ «طلب مالي جديد» و«طلباتي المالية» في شاشةٍ واحدة';
$S['canonical'] = $rows($conn, "SELECT route, canonical_ar, old_names, current_label, status,
                                       decision_state, application_state, decision_source, decided_at,
                                       merge_into, retirement_status
                                  FROM nav_canonical WHERE route IN ('" . $esc($KEEP) . "', '" . $esc($GONE) . "')");
$run($conn, "UPDATE nav_canonical
                SET canonical_ar = '" . $esc($LABEL) . "',
                    current_label = '" . $esc($LABEL) . "',
                    old_names = CASE WHEN old_names IS NULL OR TRIM(old_names) IN ('', '—')
                                     THEN 'طلب مالي جديد'
                                     ELSE CONCAT(old_names, ' · ', 'طلب مالي جديد') END,
                    status = 'APPROVED', decision_state = 'APPROVED', application_state = 'DEPLOYED',
                    decision_source = '" . $esc($SRC) . "', decided_at = NOW()
              WHERE route = '" . $esc($KEEP) . "'");
$run($conn, "UPDATE nav_canonical
                SET status = 'MERGED', retirement_status = 'MERGE_THEN_REDIRECT',
                    merge_into = 'يُدمج في " . $esc($KEEP) . " — إحصاءٌ وفلاترُ وقائمةٌ ونموذجٌ في شاشةٍ واحدة · والملفُّ يبقى مُحوِّلًا',
                    decision_source = '" . $esc($SRC) . "', decided_at = NOW()
              WHERE route = '" . $esc($GONE) . "'");
echo "⑦ nav_canonical: الباقيةُ APPROVED باسمِ «{$LABEL}» و«طلب مالي جديد» في `old_names` · والمتقاعدُ MERGED\n";

/* ── ⑧ اللقطةُ تُحفظ بعدَ نجاحِ كلِّ خطوة ─────────────────────────────── */
if (!is_dir($STATE_DIR)) { @mkdir($STATE_DIR, 0777, true); }
file_put_contents($STATE, json_encode($S, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* ── ⑧ الشاهد: يُعادُ القياسُ من القاعدةِ لا يُصدَّق مُرجَعُ التنفيذِ وحدَه ── */
echo "───────────────────────────────────────────────────────────────────\n";
$liveGone = (int) $conn->query("SELECT COUNT(*) c FROM nav_items WHERE route = '" . $esc($GONE) . "' AND active = 1")->fetch_assoc()['c'];
$liveKeep = (int) $conn->query("SELECT COUNT(*) c FROM nav_items WHERE route = '" . $esc($KEEP) . "' AND active = 1")->fetch_assoc()['c'];
$canonGone = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical_current WHERE route = '" . $esc($GONE_KEY) . "'")->fetch_assoc()['c'];
$dupe = (int) $conn->query("SELECT COUNT(*) c FROM (
        SELECT role_id FROM nav_items WHERE active = 1 AND route IN ('" . $esc($KEEP) . "', '" . $esc($GONE) . "')
         GROUP BY role_id HAVING COUNT(*) > 1) x")->fetch_assoc()['c'];
/* «الممنوحُ يُرى» (2027_02_13-①): لا يبقى منحٌ على وحدةٍ صفُّها مُطفأ */
$ghost = (int) $conn->query("SELECT COUNT(*) c FROM role_permissions WHERE module_id = {$MR}")->fetch_assoc()['c'];
$lost  = (int) $conn->query("SELECT COUNT(*) c FROM (
        SELECT p.role_id FROM role_permissions p
         WHERE p.module_id = {$RF} AND p.can_view = 1
           AND NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = p.role_id
                             AND n.route = '" . $esc($KEEP) . "' AND n.active = 1)) x")->fetch_assoc()['c'];
printf("الشاهد · صفوفٌ حيّةٌ على المتقاعد: %d (المطلوب 0)\n", $liveGone);
printf("الشاهد · صفوفٌ حيّةٌ على الباقية : %d\n", $liveKeep);
printf("الشاهد · صفوفُ السجلِّ للمتقاعد  : %d (المطلوب 0)\n", $canonGone);
printf("الشاهد · أدوارٌ برابطَين         : %d (المطلوب 0)\n", $dupe);
printf("الشاهد · منحٌ على وحدةٍ متقاعدة  : %d (المطلوب 0)\n", $ghost);
printf("الشاهد · ممنوحٌ بلا رابطٍ حيّ    : %d (يُعلَن ولا يُسكت عنه)\n", $lost);

$bad = ($liveGone !== 0) + ($canonGone !== 0) + ($dupe !== 0) + ($ghost !== 0);
echo "───────────────────────────────────────────────────────────────────\n";
echo ($bad === 0 ? "✔ اكتمل" : "✘ اكتمل بـ{$bad} شاهدًا مخالفًا")
   . " · اللقطة: storage/backups/finreq_merge_20260821/state.json\n";
echo "  ثم: php database/migrate.php dump-schema\n";
exit($bad === 0 ? 0 : 1);
