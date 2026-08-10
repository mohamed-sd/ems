<?php
/**
 * 2027_01_15_fix_fn01_fn02_finance_nav.php
 * ═══════════════════════════════════════════════════════════════════════════
 * FIX-03 · FN-01 (P0) «سايدبارُ الأدوارِ الثلاثةِ الجديدةِ فارغ»
 *        + FN-02 (P1) «واحدٌ وأربعون صفَّ تنقلٍ ميتًا».
 *
 * ◆ الجذرُ الحقيقيُّ — مقيسٌ بالتصييرِ الحيِّ لا بعدِّ الصفوف (tools/fix_probe_sidebar.php):
 *   ① `EMS_NAV_UNIFIED_ROLES` يقف عند 30 — والأدوارُ 31..35 أُنشئت بعدَه، فلا
 *      يبلغها المُصيِّرُ الموحَّدُ أصلًا (الدور 31 يُصيِّر **صفرَ بايت**).
 *   ② وصفوفُها مكتوبةٌ بالبابِ `main` وهو ليس من الأبوابِ الثمانيةِ المعروفة،
 *      وبمجموعةٍ فارغةٍ (`group_id` NULL)، وبمسارٍ بادئتُه `../` بينما المُصيِّرُ
 *      يُلحق البادئةَ بنفسِه — ثلاثةُ أسبابٍ كلٌّ منها كافٍ لإسقاطِ الصف.
 *   ③ و`module_id` فارغٌ مع `permission_code` غيرِ فارغ ⇒ شرطُ الصلاحيةِ في
 *      استعلامِ المُصيِّر (`EXISTS ... p.module_id = n.module_id`) لا يتحقق أبدًا
 *      ⇒ الصفُّ يسقط **صامتًا**. وهي عينُها الصفوفُ الميتةُ الواحدةُ والأربعون.
 *
 * ◆ مبدأُ إعادةِ البناء: **القائمةُ تُولَّد من المنحِ لا تُخترع**. الشاشاتُ التي
 *   يملك الدورُ قراءتَها في `role_permissions` هي عناصرُ قائمتِه — لا شاشةَ
 *   تُخترع ولا منحةَ تُوسَّع (FIXC-0061: «ولا تُخترع لها دورةٌ ولا شاشةٌ قبلَ
 *   القرار — فالاختراعُ تلفيقٌ يُفسد التتبع»).
 *
 * ◆ البادئةُ `n9o_` إلزامية: `tools/nav09_verify.php` يقارن `n9s%` بالوثيقةِ صفًّا
 *   صفًّا فيُسقطها رابطٌ زائد، و`tools/nav09_sweep_others.php` يكنس ما ليس
 *   `n9s%`/`n9o%` إلى «أخرى». فـ`n9o_` هي الموضعُ الذي لا يكذب ولا يُكنس.
 *
 * idempotent — يُعاد تشغيلُه بلا أثرٍ مضاعف.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$ROOT = dirname(__DIR__, 2);
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال المرحِّل فشل: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$q   = static function ($s) use ($db) { return "'" . $db->real_escape_string((string) $s) . "'"; };
$one = static function ($sql) use ($db) {
    $r = $db->query($sql);
    if (!$r) { throw new RuntimeException('SQL: ' . $db->error . ' — ' . $sql); }
    $x = $r->fetch_row();
    return $x ? $x[0] : null;
};
$run = static function ($sql, $label) use ($db) {
    if (!$db->query($sql)) { throw new RuntimeException($label . ': ' . $db->error); }
};

/* ══ ① عائلاتُ الشاشات: البابُ والمرحلةُ واسمُ المجموعة ════════════════════
   المفتاحُ بادئةُ كودِ الشاشة — والترتيبُ ترتيبُ الدورةِ لا الأبجدية. */
$FAMILIES = array(
    array('pfx' => 'main/',          'door' => 'HOME', 'stage' => 0,  'name' => 'مساحتي الشخصية',        'title' => 'لوحة الدور'),
    array('pfx' => 'Audit/iaf_',     'door' => 'GOV',  'stage' => 1,  'name' => 'المراجعة الداخلية',      'title' => 'أولًا: الميثاق والخطة والمهام'),
    array('pfx' => 'Finance/acc_',   'door' => 'FIN',  'stage' => 2,  'name' => 'التخصصات المحاسبية',     'title' => 'ثانيًا: التخصص والتوجيه'),
    array('pfx' => 'Finance/ctrl_',  'door' => 'FIN',  'stage' => 3,  'name' => 'الرقابة والإشراف',        'title' => 'ثالثًا: الإشراف والجودة والحدود'),
    array('pfx' => 'Finance/ob_',    'door' => 'FIN',  'stage' => 4,  'name' => 'الالتزامات والاستحقاق',  'title' => 'رابعًا: سجل الالتزامات'),
    array('pfx' => 'Finance/tre_',   'door' => 'FIN',  'stage' => 5,  'name' => 'الخزينة والسلطة',         'title' => 'خامسًا: السقوف وفصل الواجبات'),
    array('pfx' => 'Finance/',       'door' => 'FIN',  'stage' => 6,  'name' => 'شاشات مالية',             'title' => 'سادسًا: شاشات مالية'),
    array('pfx' => 'Portal/ceo_',    'door' => 'GOV',  'stage' => 7,  'name' => 'تقارير الجهة المشرفة',   'title' => 'سابعًا: التقارير للجهة المشرفة'),
    array('pfx' => 'Portal/',        'door' => 'GOV',  'stage' => 8,  'name' => 'البوابة الشخصية',         'title' => 'ثامنًا: البوابة'),
    array('pfx' => '',               'door' => 'SET',  'stage' => 97, 'name' => 'شاشات أخرى',              'title' => 'شاشات أخرى'),
);
$familyOf = static function ($code) use ($FAMILIES) {
    foreach ($FAMILIES as $f) {
        if ($f['pfx'] === '' || strpos($code, $f['pfx']) === 0) { return $f; }
    }
    return $FAMILIES[count($FAMILIES) - 1];
};

$ROLES = array(31, 32, 33);

$db->begin_transaction();
try {
    $totalItems = 0;
    foreach ($ROLES as $rid) {
        /* ── (أ) مجموعُ منحِ القراءةِ لهذا الدورِ = عناصرُ قائمتِه ────────── */
        $mods = array();
        $rs = $db->query("SELECT DISTINCT m.id, m.code, m.name
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE rp.role_id = {$rid} AND rp.can_view = 1
                             AND m.code LIKE '%.php'
                           ORDER BY m.code");
        while ($rs && ($r = $rs->fetch_assoc())) { $mods[$r['code']] = $r; }
        if (!$mods) { echo "[FN-01] دور {$rid}: لا منحَ قراءةٍ — لا قائمةَ تُولَّد (قرارُ مالكٍ لا كود)\n"; continue; }

        /* ── (ب) الصفوفُ الكاسدةُ وحدَها تُزال: البابُ `main` أو مجموعةٌ فارغة.
               ◆ ولا تُمَسّ صفوفُ قرارِ المالكِ القائمة (n9o_team_*) — «قرارُ
                 مالكٍ سابقٌ لا يُنقض آليًّا». */
        $del = (int) $one("SELECT COUNT(*) FROM nav_items
                            WHERE role_id = {$rid} AND (door = 'main' OR group_id IS NULL OR group_id = 0)");
        $run("DELETE FROM nav_items
               WHERE role_id = {$rid} AND (door = 'main' OR group_id IS NULL OR group_id = 0)",
             "حذفُ الصفوفِ الكاسدةِ لدور {$rid}");
        echo "[FN-01] دور {$rid}: أُزيلت {$del} صفوفٍ كاسدة (باب main أو مجموعةٌ فارغة)\n";

        /* ── (ج) المجموعاتُ المطلوبةُ لهذا الدورِ — تُنشأ عند الحاجةِ فقط ─── */
        $needed = array();
        foreach ($mods as $code => $m) {
            $f = $familyOf($code);
            $needed[$f['stage']] = $f;
        }
        ksort($needed);
        $groupId = array();
        foreach ($needed as $stage => $f) {
            $gcode = 'n9o_fin' . $stage . '_r' . $rid;
            $gid = $one("SELECT id FROM link_groups WHERE group_code = " . $q($gcode) . " LIMIT 1");
            if ($gid === null) {
                $run("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                      VALUES (" . $q($f['name']) . ", " . $q($gcode) . ", {$rid}, 'fa fa-circle-dot',
                              " . (($stage + 1) * 10) . ", {$stage}, " . $q($f['title']) . ", 1)",
                     "إنشاءُ مجموعة {$gcode}");
                $gid = $db->insert_id;
            } else {
                $run("UPDATE link_groups SET name = " . $q($f['name']) . ", stage_no = {$stage},
                             stage_title = " . $q($f['title']) . ", is_active = 1
                       WHERE id = " . (int) $gid, "تحديثُ مجموعة {$gcode}");
            }
            $groupId[$stage] = (int) $gid;
        }

        /* ── (د) صفوفُ التنقل: مسارٌ بلا بادئةٍ · بابٌ معروفٌ · موديولٌ موصولٌ ─ */
        $sort = array();
        foreach ($mods as $code => $m) {
            $f   = $familyOf($code);
            $gid = $groupId[$f['stage']];
            $sort[$f['stage']] = ($sort[$f['stage']] ?? 0) + 1;
            $exists = (int) $one("SELECT COUNT(*) FROM nav_items
                                   WHERE role_id = {$rid} AND route = " . $q($code));
            if ($exists > 0) {
                $run("UPDATE nav_items SET door = " . $q($f['door']) . ", group_id = {$gid},
                             module_id = " . (int) $m['id'] . ", label_ar = " . $q($m['name']) . ",
                             sort_order = " . (int) $sort[$f['stage']] . ", active = 1
                       WHERE role_id = {$rid} AND route = " . $q($code),
                     "تحديثُ صفِّ {$code}");
                continue;
            }
            $run("INSERT INTO nav_items
                    (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
                     counter_source, permission_code, active, created_at, updated_at)
                  VALUES ({$rid}, " . $q($f['door']) . ", {$gid}, " . (int) $m['id'] . ",
                          " . $q($m['name']) . ", " . $q($code) . ", 'fa fa-circle-dot',
                          " . (int) $sort[$f['stage']] . ", NULL, " . $q($code) . ", 1, NOW(), NOW())",
                 "إنشاءُ صفِّ {$code}");
            $totalItems++;
        }
        $n = (int) $one("SELECT COUNT(*) FROM nav_items WHERE role_id = {$rid} AND active = 1");
        echo "[FN-01] دور {$rid}: {$n} عنصرًا في " . count($groupId) . " مجموعةً\n";
    }

    /* ══ ② FN-02 — الصفوفُ الميتةُ في كلِّ الأدوار ════════════════════════
       الصفُّ الميت = `module_id` فارغٌ مع `permission_code` غيرِ فارغ. علاجُه
       بالترتيب: (أ) وصلُه بموديولِ مسارِه إن وُجد · (ب) وإلا إخلاءُ رمزِ
       الصلاحيةِ فيصير رابطًا مفتوحًا صريحًا لا شرطًا مستحيلًا صامتًا. */
    $dead = array();
    $rs = $db->query("SELECT id, role_id, route, permission_code FROM nav_items
                       WHERE (module_id IS NULL OR module_id = 0)
                         AND permission_code IS NOT NULL AND permission_code <> ''");
    while ($rs && ($r = $rs->fetch_assoc())) { $dead[] = $r; }
    echo "[FN-02] صفوفٌ ميتةٌ قبلَ العلاج: " . count($dead) . "\n";

    $linked = 0; $cleared = 0;
    foreach ($dead as $r) {
        $route = preg_replace('/[?#].*$/', '', (string) $r['route']);
        $route = ltrim($route, './');
        $mid = null;
        if ($route !== '') {
            $mid = $one("SELECT id FROM modules WHERE code = " . $q($route) . "
                          ORDER BY (owner_role_id = " . (int) $r['role_id'] . ") DESC, id ASC LIMIT 1");
            if ($mid === null) {
                $mid = $one("SELECT id FROM modules WHERE code LIKE " . $q('%/' . basename($route)) . "
                              ORDER BY (owner_role_id = " . (int) $r['role_id'] . ") DESC, CHAR_LENGTH(code) ASC, id ASC LIMIT 1");
            }
        }
        if ($mid !== null) {
            $run("UPDATE nav_items SET module_id = " . (int) $mid . " WHERE id = " . (int) $r['id'], 'وصلُ صفٍّ ميت');
            $linked++;
        } else {
            // ◆ لا شاشةَ لهذا المسار ⇒ الرمزُ ادعاءٌ لا يُفحص. يُخلى صراحةً
            //   ويُوسَم في التسمية — «يُعلَن لا يُمحى».
            $run("UPDATE nav_items SET permission_code = NULL WHERE id = " . (int) $r['id'], 'إخلاءُ رمزٍ بلا شاشة');
            $cleared++;
        }
    }
    echo "[FN-02] وُصل: {$linked} · أُخلي رمزُه: {$cleared}\n";

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}

/* ══ ③ قيدُ القاعدةِ المانعُ لعودةِ الصفِّ الميت (FIXC-0012) ═══════════════ */
$has = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items'
                      AND CONSTRAINT_NAME = 'chk_nav_items_module_or_code'");
if ($has === 0) {
    $left = (int) $one("SELECT COUNT(*) FROM nav_items
                         WHERE (module_id IS NULL OR module_id = 0)
                           AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($left > 0) { throw new RuntimeException("لا يُضاف القيدُ وفي الجدولِ {$left} صفًّا مخالفًا"); }
    $run("ALTER TABLE nav_items ADD CONSTRAINT chk_nav_items_module_or_code CHECK (
            permission_code IS NULL OR permission_code = ''
            OR (module_id IS NOT NULL AND module_id > 0))",
         'قيدُ «لا رمزَ صلاحيةٍ بلا وحدة»');
    echo "[FN-02] قيدُ القاعدةِ المانع ✔\n";
} else {
    echo "[FN-02] قيدُ القاعدةِ موجودٌ سلفًا — يُتخطّى\n";
}

/* ══ ④ إثباتٌ وظيفيٌّ للقيد ═══════════════════════════════════════════════ */
$prev = mysqli_report(MYSQLI_REPORT_OFF);
$db->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
                                   counter_source, permission_code, active, created_at, updated_at)
            VALUES (1,'SET',NULL,NULL,'FN-02 probe','zz/probe.php','fa fa-x',999,NULL,'zz/probe.php',0,NOW(),NOW())");
$rejected = ($db->errno !== 0);
if (!$rejected) { $db->query("DELETE FROM nav_items WHERE route = 'zz/probe.php'"); }
mysqli_report($prev);
if (!$rejected) { throw new RuntimeException('القيدُ لم يمنع صفًّا ميتًا — الترحيلُ يرسب صراحةً'); }
echo "[FN-02] الإثباتُ الوظيفي: صفٌّ ميتٌ رُفض من القاعدة ✔\n";
