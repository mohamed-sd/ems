<?php
/**
 * tools/ctl_build_register.php — تسجيلُ سطحٍ مبنيٍّ حديثًا بكاملِ سلسلتِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **البناءُ ليس ملفًّا يقع على القرص** — سبعُ حلقاتٍ يفقد بغيابِ أيِّها بابَه
 *   أو حارسَه أو حكمَه:
 *   ① `modules` (‏هويّةُ الصلاحيّة) · ② `role_permissions` — **والمنحُ يُنسخ من
 *   جارٍ مقيسٍ لا يُخترع** · ③ `repair01_screen_registry` بمعرِّفٍ جديدٍ ·
 *   ④ `nav_canonical` — **الاسمُ والمجموعةُ والتسلسلُ من الملفِّ التصميميِّ
 *   حرفًا** و`decision_source` يسمّيه (‏فلا يُلفَّق اعتماد) · ⑤ `nav_items`
 *   لكلِّ دورٍ ممنوحٍ (‏بابُ الجارِ ومجموعتُه بقاعدةِ س٣) · ⑥ `target_universe`
 *   ⇒ `MATCHED` بشاهدِ البناء · ⑦ حالةُ المتطلبِ ⇒ `IMPLEMENTED_NOT_VERIFIED`
 *   — ⛔ **لا `EVIDENCE_CLOSED`**: الإغلاقُ بالدليلِ لمسارِه بعقودِه.
 *
 * التشغيل:
 *   php tools/ctl_build_register.php --route=… --req=… --target=… \
 *        --label="…" --icon="fa fa-…" --sibling=… [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};
$arg = function ($k, $d = '') use ($argv) {
    foreach ($argv as $a) { if (strpos($a, "--$k=") === 0) { return substr($a, strlen($k) + 3); } }
    return $d;
};
$APPLY   = in_array('--apply', $argv, true);
$route   = trim($arg('route'), '/');
$req     = $arg('req');
$target  = $arg('target');
$label   = $arg('label');
$icon    = $arg('icon', 'fa fa-table');
$sibling = trim($arg('sibling'), '/');

if ($route === '' || $req === '' || $target === '' || $label === '' || $sibling === '') {
    exit("الاستعمال: --route= --req= --target= --label= --sibling= [--icon=] [--apply]\n");
}
$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/* ═══ شروطٌ صلبةٌ قبل أيِّ كتابة ═════════════════════════════════════════ */
if (!is_file($ROOT . '/' . $route)) { exit("⛔ الملفُّ غيرُ موجودٍ على القرص: $route\n"); }
$lint = array(); $rc = 0;
@exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($ROOT . '/' . $route) . ' 2>&1', $lint, $rc);
if ($rc !== 0) { exit("⛔ الملفُّ لا يمرُّ php -l:\n" . implode("\n", $lint) . "\n"); }
$head = (string) file_get_contents($ROOT . '/' . $route, false, null, 0, 4000);
if (strpos($head, $req) === false) { exit("⛔ رأسُ الملفِّ لا يذكر `$req` — والمطالبةُ الحرفيّةُ شرطُ الدار\n"); }
if ((int) $one("SELECT COUNT(*) FROM modules WHERE code = '" . $e($route) . "'") > 0) { exit("⛔ المسارُ مسجَّلٌ في modules سلفًا\n"); }
$rq = $conn->query("SELECT unit, surface, group_name, seq, grain, source_of_truth FROM repair01_requirements
                     WHERE requirement_id = '" . $e($req) . "'")->fetch_assoc();
if (!$rq) { exit("⛔ لا متطلبَ بهذا المعرِّف\n"); }
$tu = $conn->query("SELECT verdict, unit FROM repair01_target_universe WHERE target_uid = '" . $e($target) . "'")->fetch_assoc();
if (!$tu) { exit("⛔ لا هدفَ بهذا المعرِّف\n"); }
if ($tu['verdict'] !== 'NOT_BUILT') { exit("⛔ حكمُ الهدفِ `{$tu['verdict']}` لا `NOT_BUILT` — لا يُبنى المبنيّ\n"); }
$br = $conn->query("SELECT build_ready, build_blocker FROM repair01_build_ready WHERE target_uid = '" . $e($target) . "'")->fetch_assoc();
if (!$br || $br['build_ready'] !== 'YES') {
    exit("⛔ البوّابةُ لا تجيز البناءَ: " . ($br ? $br['build_blocker'] : 'لا صفَّ في البوّابة') . "\n");
}

/* الجارُ المقيس */
$sib = $conn->query("SELECT m.id mid, m.owner_role_id, m.display_order, n.door
                       FROM modules m LEFT JOIN nav_items n ON n.route = m.code AND n.active = 1
                      WHERE m.code = '" . $e($sibling) . "' LIMIT 1")->fetch_assoc();
if (!$sib) { exit("⛔ الجارُ غيرُ مسجَّل: $sibling\n"); }
$roles = array();
$r = $conn->query("SELECT rp.role_id, rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
                     FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = '" . $e($sibling) . "'");
while ($r && ($x = $r->fetch_assoc())) { $roles[] = $x; }
if (!$roles) { exit("⛔ الجارُ بلا منحٍ واحدٍ — لا يصلح مصدرَ نسخ\n"); }
/* بابُ القراءةِ المشتقّةِ `REP` لا `DAILY` — صفُّ المصفوفةِ (⑨) يصرِّح
   «4 — التقارير والتحليلات» فبابُ الرابطِ يطابقه، و`DAILY` المكتظُّ رسَّب
   U9 (قِيس في VP-02: قسمُ الدورِ 13 اليوميُّ 9 ⇒ 10 فانكسر حدُّ ف٧-٢) */
$door = $sib['door'] !== null && $sib['door'] !== '' ? $sib['door'] : 'REP';

$newSid = 'SCR-' . str_pad((string) (1 + (int) substr((string) $one("SELECT MAX(screen_id) FROM repair01_screen_registry"), 4)), 4, '0', STR_PAD_LEFT);
$dGroup = (string) $rq['group_name'];
$dSeq   = (int) $rq['seq'];

echo "\n═══ تسجيلُ سطحٍ مبنيّ — $route ═══\n";
printf("  المتطلب %s [%s] · الهدف %s · المعرِّفُ الجديد %s\n", $req, $rq['unit'], $target, $newSid);
printf("  الاسمُ «%s» · المجموعةُ (من الملفِّ حرفًا) «%s» · التسلسل %d · البابُ %s\n", $label, $dGroup, $dSeq, $door);
printf("  المنحُ منسوخٌ من الجارِ `%s`: %d دورًا (قراءةً — والأفعالُ تُصفَّر لقراءةٍ صِرف)\n", $sibling, count($roles));
if (!$APPLY) { echo "\n  ⛔ معاينةٌ — والتطبيقُ بـ--apply\n"; exit(0); }

/* ═══ الكتابةُ السباعيّة ═════════════════════════════════════════════════ */
$conn->query('START TRANSACTION');
$mid = 1 + (int) $one("SELECT MAX(id) FROM modules");
/* جارٌ بلا دورِ مالكٍ يُنسَخ NULL لا صفرًا — صفرٌ يكسر قيدَ FK نحو roles */
$ownSql = $sib['owner_role_id'] === null ? 'NULL' : (string) (int) $sib['owner_role_id'];
$ok = $conn->query("INSERT INTO modules (id, name, code, owner_role_id, icon, display_order, owner_dept_note)
        VALUES ($mid, '" . $e($label) . "', '" . $e($route) . "', $ownSql,
                '" . $e($icon) . "', $dSeq, '" . $e($rq['unit'] . ' · ' . $req) . "')");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ modules: {$conn->error}\n"); }
foreach ($roles as $x) {
    /* قراءةٌ صِرفٌ: can_view يُنسخ والأفعالُ تُصفَّر — فلا فعلَ في الشاشةِ أصلًا */
    $ok = $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
            VALUES (" . (int) $x['role_id'] . ", $mid, " . (int) $x['can_view'] . ", 0, 0, 0)");
    if (!$ok) { $conn->query('ROLLBACK'); exit("✘ perm: {$conn->error}\n"); }
}
/* ②·ب **بندُ القالبِ — بالقناةِ القائمةِ لا بغيرِها**: طبقةُ GOV-AUTH-01
   تمنع المغطَّى عن كلِّ شاشةٍ خارجَ قالبِه («لا شاشةَ خارجَ القالب»)، وكلُّ
   مستخدمي co4 الممنوحين مغطَّون — فشاشةٌ جديدةٌ بلا بندِ قالبٍ **لا يراها
   أحدٌ**. والقناةُ المرسومةُ في المخزنِ نفسِه: بذرٌ آليٌّ `seeded_from=
   role_permissions:N` وحقنٌ مسمًّى للمبنيِّ حديثًا (`injfrd66:*` سابقةً) —
   فيُضاف بندُ عرضٍ (‏allow وحدَه، والأفعالُ صفر) لكلِّ قالبٍ نشِطٍ ممنوحٍ
   لمستخدمٍ حيٍّ دورُه من الأدوارِ الممنوحةِ عرضًا. */
$profN = 0;
$roleList = array();
foreach ($roles as $x) { if ((int) $x['can_view'] === 1) { $roleList[] = (int) $x['role_id']; } }
if ($roleList) {
    $pr = $conn->query(
        "SELECT DISTINCT p.profile_id, p.company_id
           FROM gov_role_profiles p
           JOIN gov_authority_grants g ON g.profile_id = p.profile_id AND g.revoked_at IS NULL
                AND (g.valid_to IS NULL OR g.valid_to > NOW())
           JOIN users u ON u.id = g.user_id AND u.company_id = 4
          WHERE p.state = 'active' AND CAST(u.role AS UNSIGNED) IN (" . implode(',', $roleList) . ")");
    while ($pr && ($pz = $pr->fetch_assoc())) {
        $dup = $one("SELECT COUNT(*) FROM gov_profile_items
                      WHERE profile_id = " . (int) $pz['profile_id'] . " AND item_kind = 'screen'
                        AND item_ref = '" . $e($route) . "'");
        if ((int) $dup > 0) { continue; }
        $ok = $conn->query("INSERT INTO gov_profile_items
                (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
                VALUES (" . (int) $pz['company_id'] . ", " . (int) $pz['profile_id'] . ", 'screen',
                        '" . $e($route) . "', 1, 0, 0, 0, '" . $e('ctl_build:' . date('Y-m-d') . ':' . $req) . "')");
        if (!$ok) { $conn->query('ROLLBACK'); exit("✘ profile_items: {$conn->error}\n"); }
        $profN++;
    }
}

$file = basename($route);
/* قيدُ المخطَّطِ `chk_sot_witness`: مصدرُ حقيقةٍ بلا شاهدٍ يُرفض — والشاهدُ
   هنا الملفُّ التصميميُّ نفسُه بمعرِّفِ متطلبِه */
$ok = $conn->query("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule, lifecycle,
         lifecycle_rule, visibility_class, visibility_rule, on_disk, origin, guard_kind,
         canonical_label_ar, source_of_truth, sot_witness, ownership_verdict, verdict_rule)
        VALUES ('" . $e($newSid) . "', '" . $e($file) . "', '" . $e($route) . "', 'BUILD_LANE',
                '" . $e(preg_match('~^(\d{2})~', (string) $rq['unit'], $m0) ? 'DEP-' . $m0[1] : (string) $tu['unit']) . "',
                '', 'BUILD_LANE', 'LIVE_REGISTERED', 'CTL_BUILD', 'MENU_ITEM', 'NAV_ITEMS_ACTIVE',
                1, 'BUILD', 'PAGE_PERM', '" . $e($label) . "', '" . $e(mb_substr((string) $rq['source_of_truth'], 0, 180)) . "',
                '" . $e('من الملفِّ التصميميِّ حرفًا — ' . $req . ' (' . $rq['unit'] . ') · Build Lane') . "',
                'DOMAIN_PROJECTION', '" . $e('قراءةٌ مشتقّةٌ بنصِّ ' . $req) . "')");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ registry: {$conn->error}\n"); }
$ok = $conn->query("INSERT INTO nav_canonical
        (route, canonical_ar, level_no, level_name, group_name, sort_no, status,
         decision_state, decision_source, decided_at, derivation, screen_id)
        VALUES ('" . $e($route) . "', '" . $e($label) . "', 2, 'العمليات', '" . $e($dGroup) . "', $dSeq,
                'APPROVED', 'APPROVED', '" . $e('الدليل المعماري — ' . $req . ' (' . $rq['unit'] . ')') . "', NOW(),
                '" . $e('اسمُ السطحِ ومجموعتُه وتسلسلُه من الملفِّ التصميميِّ حرفًا — ' . $req) . "', '" . $e($newSid) . "')");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ canonical: {$conn->error}\n"); }

/* nav_items لكلِّ دورٍ ممنوحٍ عرضًا — والمجموعةُ بقاعدةِ س٣ (اسمُ المعتمَدِ في مجموعاتِ الدور) */
$navN = 0;
foreach ($roles as $x) {
    if ((int) $x['can_view'] !== 1) { continue; }
    $rid0 = (int) $x['role_id'];
    /* دورٌ موسومٌ «مدمج» قرارُ دمجٍ نافذٌ — لا يرث رابطًا جديدًا (قِيس في
       VP-02: الدورُ 5 صفرُ مستخدمين ورث رابطًا واحدًا فرسَب حاجبُ ⑪ النحافة) */
    $merged = $one("SELECT COUNT(*) FROM roles WHERE id = $rid0 AND name LIKE '%مدمج%'");
    if ((int) $merged > 0) { continue; }
    $gid = $one("SELECT id FROM link_groups WHERE owner_role_id = $rid0 AND is_active = 1
                  AND name = '" . $e($dGroup) . "' LIMIT 1");
    if ($gid === null) {
        /* مجموعةٌ جديدةٌ بلا مرحلةٍ يسقط رابطُها في رأسِ المرحلةِ الافتراضيّةِ
           المكتظِّ (قِيس في VP-02: قسمُ الدورِ 13 بلغ 10 فرسَب U9) — فتُولد
           برأسِ طيٍّ باسمِها هي، ورقمُ مرحلتِها مؤخَّرٌ كي لا يزاحم القائم */
        $okg = $conn->query("INSERT INTO link_groups (name, owner_role_id, icon, display_order, is_active, stage_no, stage_title)
                VALUES ('" . $e($dGroup) . "', $rid0, 'fa fa-folder', $dSeq, 1, 90, '" . $e($dGroup) . "')");
        if (!$okg) { $conn->query('ROLLBACK'); exit("✘ group: {$conn->error}\n"); }
        $gid = $conn->insert_id;
    }
    $ok = $conn->query("INSERT INTO nav_items
            (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
            VALUES ($rid0, '" . $e($door) . "', " . (int) $gid . ", $mid, '" . $e($label) . "',
                    '" . $e($route) . "', '" . $e($icon) . "', $dSeq, '" . $e($route) . "', 1)");
    if (!$ok) { $conn->query('ROLLBACK'); exit("✘ nav: {$conn->error}\n"); }
    $navN++;
}
/* رأسُ الطيِّ المُصيَّرُ من `nav_route_group` (مفتاحُه المسارُ وحدَه) —
   ومسارٌ بلا صفٍّ يسقط في رأسِ «التشغيل اليومي» المكتظِّ فيُرسِّب U9
   (قِيس في VP-02). والقراءةُ المشتقّةُ بابُها `REPORTS` كما يصرِّح صفُّ
   المصفوفةِ «4 — التقارير والتحليلات». */
$ok = $conn->query("INSERT INTO nav_route_group (route, group_code, basis)
        VALUES ('" . $e($route) . "', 'REPORTS', '" . $e('BUILD_LANE · ' . $req . ' · قراءة مشتقة') . "')
        ON DUPLICATE KEY UPDATE basis = VALUES(basis)");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ route_group: {$conn->error}\n"); }
$wit = 'BUILD_LANE · بُني من الملفِّ التصميميِّ (' . $req . ' · ' . $rq['unit'] . ') بعد بوّابةِ BUILD_READY '
     . 'وحقولُه من `repair01_fields` — والرأسُ يذكر المعرِّفَ حرفًا · لقطة ' . $snap;
$ok = $conn->query("UPDATE repair01_target_universe
        SET verdict = 'MATCHED', screen_id = '" . $e($newSid) . "', verdict_witness = '" . $e($wit) . "',
            verdict_snapshot = '" . $e($snap) . "', verdict_at = NOW()
      WHERE target_uid = '" . $e($target) . "' AND verdict = 'NOT_BUILT'");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ universe: {$conn->error}\n"); }
$ok = $conn->query("UPDATE repair01_requirements
        SET amd01_state = 'IMPLEMENTED_NOT_VERIFIED',
            state_evidence = '" . $e('بُني في Build Lane — ' . $route . ' · والإثباتُ لمسارِ الإغلاقِ بعقودِه') . "',
            state_at = NOW(), state_snapshot = '" . $e($snap) . "'
      WHERE requirement_id = '" . $e($req) . "'");
if (!$ok) { $conn->query('ROLLBACK'); exit("✘ requirement: {$conn->error}\n"); }
$conn->query('COMMIT');

/* ⑨ **صفُّ مصفوفةِ العرض** — بوّابةُ U1 ترسُب على مُصيَّرٍ بلا صفٍّ فيها،
   والسابقةُ قائمة (صفوفُ 3115+ للمبنيِّ حديثًا بمرجعِ موجتِه) */
$csv = $ROOT . '/docs/uxui_matrix_20260818.csv';
$has = strpos((string) file_get_contents($csv), $route) !== false;
if (!$has) {
    $maxN = 0;
    foreach (file($csv) as $l0) { if (preg_match('~^(\d+),~', $l0, $m1)) { $maxN = max($maxN, (int) $m1[1]); } }
    $q0 = function ($s0) { return '"' . str_replace('"', '""', $s0) . '"'; };
    $row = ($maxN + 1) . ',' . $route . ',' . $q0($label) . ',' . $q0($label) . ',"",—,'
         . $q0('من الملفِّ التصميميِّ — ' . $req . '. قراءةٌ مشتقّةٌ ولا إدخالَ فيها.') . ','
         . $q0($rq['unit']) . ',"4 — التقارير والتحليلات",' . $q0($dGroup) . ',' . $dSeq
         . ',تقرير,1,' . $q0($rq['unit']) . ',"قدرةٌ ثبت غيابُها فبُنيت في موضعِها المعياريّ",APPROVED,'
         . $q0('الدليل المعماري — ' . $req . ' · Build Lane') . ',—,—,ACTIVE,—,'
         . $q0($label) . ',' . $q0($dGroup) . ',' . $q0('قرارُ الورقة ' . $req) . ',' . $q0($dGroup) . "\n";
    file_put_contents($csv, $row, FILE_APPEND);
    echo "  ✔ أُلحق صفُّ مصفوفةِ العرضِ #" . ($maxN + 1) . "\n";
}
/* ⑩ **ملفُّ المساحاتِ — نسخُ صفوفِ الجارِ بهجائِها الحيّ**: سقّاطةُ NF-24
   ترفض مسارًا نشطًا بلا صفٍّ في `gov_space_appearances` («الانفتاحُ
   الافتراضيُّ مستهلَك»)، والهجاءُ يُنسخ من صفوفِ الجارِ حرفًا — ⛔ فهجاءٌ
   ثالثٌ مفردةٌ جديدةٌ في عمودٍ محكوم. */
/* ⛔ **النسخُ كاملُ الأعمدةِ** — `id` بلا توليدٍ ذاتيٍّ فإدخالٌ ناقصُه يسقط
   صامتًا (قِيس في FLEET-30 فسقطت الخطوةُ كلُّها ٤ مرّاتٍ بلا سطرِ خطأ). */
$spN = 0;
$spCols = array();
$sq = $conn->query("SHOW COLUMNS FROM gov_space_appearances");
while ($sq && ($sz = $sq->fetch_assoc())) { $spCols[] = $sz['Field']; }
$already = (int) $one("SELECT COUNT(*) FROM gov_space_appearances WHERE route = '" . $e($route) . "'");
if ($already === 0 && $spCols) {
    $sel = array();
    foreach ($spCols as $co) {
        if ($co === 'id') { $sel[] = '@ctlnid := @ctlnid + 1'; }
        elseif ($co === 'route') { $sel[] = "'" . $e($route) . "'"; }
        elseif ($co === 'screen_ar') { $sel[] = "'" . $e($label) . "'"; }
        elseif ($co === 'basis') { $sel[] = "CONCAT('نُسخ ملفُّ ظهورِ الجارِ مع البناء — " . $e($req) . " · ', `basis`)"; }
        else { $sel[] = "`$co`"; }
    }
    $conn->query('SET @ctlnid = (SELECT MAX(id) FROM gov_space_appearances)');
    $ok = $conn->query("INSERT INTO gov_space_appearances (`" . implode('`,`', $spCols) . "`)
            SELECT " . implode(',', $sel) . " FROM gov_space_appearances
             WHERE route = '" . $e($sibling) . "'");
    if (!$ok) { $conn->query('ROLLBACK'); exit("✘ appearances: {$conn->error}\n"); }
    $spN = $conn->affected_rows;
    /* جارٌ بلا صفِّ ظهورٍ أصلًا — والصفرُ الصادقُ يُرسِّب NF-24 («مسارٌ نشِطٌ
       خارجَ سجلِّ التصنيف» — قِيس في VP-02): يُصنَّف المسارُ بنفسِه من
       الورقةِ صفًّا واحدًا OWNED لمساحةِ وحدتِه، والهجاءُ هجاءُ المخزنِ الحيّ */
    if ($spN === 0) {
        $SPACE_BY_UNIT = array('E1 مساحة الرئيس التنفيذي' => 'الرئيس التنفيذي', 'E2 مساحة النواب' => 'نواب الرئيس');
        $spaceAr = isset($SPACE_BY_UNIT[(string) $rq['unit']]) ? $SPACE_BY_UNIT[(string) $rq['unit']] : (string) $rq['unit'];
        $nid2 = 1 + (int) $one("SELECT MAX(id) FROM gov_space_appearances");
        $ok = $conn->query("INSERT INTO gov_space_appearances
                (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind, src_class,
                 src_ownership, src_decision, src_note, spaces_count, cls, ownership, decision, basis, rule_step, view_fields, updated_at)
                VALUES ($nid2, '" . $e($spaceAr) . "', 'DEPARTMENT', '', '" . $e($label) . "', '" . $e($route) . "',
                        '" . $e($spaceAr) . "', 'BUSINESS_DEPARTMENT', 'BUILD_LANE', 'VALID', 'CONFIRMED',
                        'سطح قراءة مشتق بني من الملف التصميمي - لا ادخال', 1, 'OWNED', 'VALID', 'CONFIRMED',
                        '" . $e('بُني من الملفِّ التصميميِّ — ' . $req . ' (' . $rq['unit'] . ') · المساحةُ هي المالكةُ في السجلِّ المعياريِّ ولا جارَ له صفُّ ظهورٍ يُنسخ') . "',
                        1, '', NOW())");
        if (!$ok) { $conn->query('ROLLBACK'); exit("✘ appearances_self: {$conn->error}\n"); }
        $spN = 1;
    }
}
/* ⑪ **صفُّ الدورةِ — بما هو حقٌّ وحدَه**: قراءةٌ مشتقّةٌ لا تُنشئ حالةً —
   يُكتب المدخلُ الحقيقيُّ (مصدرُها) واسمُ المرحلةِ من مجموعةِ الملفِّ، وتُترك
   عناصرُ الاعتمادِ فارغةً **لأنّها لا تنطبق** — ⛔ ولا تُؤلَّف دورةٌ لإكمالِ
   عدد. والهجاءُ هجاءُ صفِّ الجارِ في `dept_name` حرفًا. */
$cyN = 0;
$cz = $conn->query("SELECT dept_name FROM gov_screen_cycle
                     WHERE screen_file = '" . $e(basename($sibling)) . "' OR screen_id =
                           (SELECT screen_id FROM repair01_screen_registry WHERE route = '" . $e($sibling) . "')
                     LIMIT 1")->fetch_assoc();
/* جارٌ بلا صفِّ دورةٍ يُسقِط الخطوةَ فيرتدُّ RP-01/RP-02 (قِيس في VP-02) —
   فيُؤخذ اسمُ الإدارةِ من هجاءِ العمودِ الحيِّ لوحدةِ الورقة، لا يُخترَع */
if (!$cz) {
    $CYCLE_DEPT_BY_UNIT = array('E1 مساحة الرئيس التنفيذي' => 'مكتب الرئيس التنفيذي والنواب',
                                'E2 مساحة النواب' => 'مكتب الرئيس التنفيذي والنواب');
    if (isset($CYCLE_DEPT_BY_UNIT[(string) $rq['unit']])) {
        $dn0 = $CYCLE_DEPT_BY_UNIT[(string) $rq['unit']];
        if ((int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE dept_name = '" . $e($dn0) . "'") > 0) {
            $cz = array('dept_name' => $dn0); /* هجاءٌ حيٌّ مثبَتُ الوجودِ وحدَه يمرّ */
        }
    }
}
if ($cz && (int) $one("SELECT COUNT(*) FROM gov_screen_cycle WHERE screen_file = '" . $e($file) . "'") === 0) {
    $ok = $conn->query("INSERT INTO gov_screen_cycle
            (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
             screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact,
             stage_kind, screen_id, bridge_rule, bridge_witness, bridge_snapshot)
            VALUES (0, '" . $e($cz['dept_name']) . "', 'دورةُ الإدارة', '" . $dSeq . "', '" . $e($dGroup) . "',
                    '" . $e($dGroup) . "', '" . $e($label) . "', '" . $e($file) . "',
                    '" . $e('قراءةٌ من ' . mb_substr((string) $rq['source_of_truth'], 0, 120)) . "',
                    '', '', '', '', '', 'canonical', '" . $e($newSid) . "', 'BASENAME_UNIQUE',
                    '" . $e('سُجِّل مع البناءِ — ' . $req . ' · قراءةٌ لا تُنشئ حالةً فتُركت عناصرُ الاعتمادِ لعدمِ الانطباق') . "',
                    '" . $e($snap) . "')");
    if ($ok) { $cyN++; }
}

printf("\n  ✔ سُجِّل: modules #%d · منحٌ %d · قوالبُ %d · مساحاتٌ %d · دورةٌ %d · %s · canonical · nav %d دورًا · MATCHED\n",
       $mid, count($roles), $profN, $spN, $cyN, $newSid, $navN);
echo "  ⛔ الحالةُ `IMPLEMENTED_NOT_VERIFIED` — والإغلاقُ بالدليلِ لمسارِه\n";
