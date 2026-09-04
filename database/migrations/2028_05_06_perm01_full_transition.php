<?php
/**
 * 2028_05_06_perm01_full_transition.php — التحوّلُ الكامل: قالبٌ واحدٌ لكلِّ دور
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §2 و§6. **«قالبٌ واحد لكل دور ولا اتحاد»** بنصِّ الأمر — فالحبّةُ
 * **الدور**، والمستخدمُ يأخذ قالبَ دورِه لا قالبًا فرديًّا.
 *
 * ◆ **وهدفُ الدورِ سندُه ثلاثةٌ لا رابع** (قاعدةُ السندِ الحاكم):
 *   ① **ورقةُ الدليل** لمساحةِ دورِه — `nav_placements` المستورَدُ من
 *      «01 · الدليل المعماري.xlsx».
 *   ② **ما يُبلَغ بالنقرِ** من شاشةِ دليلٍ أو من الشريطِ العلويّ — مقيسٌ من
 *      شيفرةِ الشاشاتِ نفسِها لا من قائمةٍ يدويّة.
 *   ③ **رابطٌ نشِطٌ في سايدبارِ الدورِ نفسِه** — `nav_items` سجلٌّ محكومٌ في
 *      حملةِ NAVR، ورابطٌ نشِطٌ **قرارٌ قائمٌ** لا يُنقض بأداة.
 *   وما لا سندَ له يُزال.
 *
 * ⛔ **والسندُ الثالثُ بالدورِ لا بالمساحة**: تثبيتُه بالمساحةِ يجعل أدوارَ
 *   الإدارةِ الواحدةِ ترث روابطَ بعضِها — **وهو عينُ «الجمعِ بالقصوى» الذي يمنعه
 *   الأمر**. (مقيسٌ: التثبيتُ بالمساحةِ فتح 2,030 بابًا.) فيُضاف عمودُ `role_id`
 *   ويُقصَر بندُ الرابطِ على دورِه.
 *
 * ⛔ **والتجميدُ يُرفع مؤقّتًا مقيَّدًا بسببِه ثمَّ يُعاد** — لا التفافَ على القادح.
 * ◆ **والقوالبُ القديمةُ تُتقاعَد لا تُحذف**: `state='retired'` فيبقى القرارُ
 *   مقروءًا، ويعود العكسُ دقيقًا بعلامةِ الماء.
 *
 * التشغيل: php database/migrations/2028_05_06_perm01_full_transition.php
 * العكس:   php database/migrations/2028_05_06_perm01_full_transition_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };
$fail = function ($w) { exit("رفض التشغيل: {$w}\n"); };
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4";

echo "══ ⓪ الحرّاس ══════════════════════════════════════════════════════════\n";
$tn = (int) $one('SELECT COUNT(*) FROM perm01_target_item');
$ws = (int) $one('SELECT COUNT(*) FROM perm01_target_profile');
printf("   سجلُّ الأهداف: %d مساحةً · %d بندًا\n", $ws, $tn);
if ($tn < 100) { $fail('سجلُّ الأهدافِ ناقص — شغِّل 2028_05_04 ثمَّ إغلاقَ الوصول'); }
$rl = (int) $one('SELECT COUNT(DISTINCT role_id) FROM nav_ws_roles');
printf("   أدوارٌ مربوطةٌ بمساحة: %d من %d\n", $rl, (int) $one('SELECT COUNT(*) FROM roles'));
if ($rl !== (int) $one('SELECT COUNT(*) FROM roles')) { $fail('دورٌ بلا مساحةٍ حاكمة'); }
$fz = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active = 1");
printf("   التجميدُ النافذ: %d مدًى\n", $fz);
$guardWired = strpos((string) @file_get_contents($ROOT . '/app/Services/Finance/BankReconService.php'),
                     'ReconSodGuard') !== false;
printf("   ضابطُ SOD-06 على الفعلِ موصول: %s\n", $guardWired ? 'نعم' : 'لا');
if (!$guardWired) { $fail('لا يُوسَّع الوصولُ قبلَ نفاذِ ضابطِ الفعل'); }
$live = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE}");
printf("   مستخدمون أحياء: %d\n", $live);
if ($live < 1) { $fail('لا مستخدمين'); }
echo "   الحرّاسُ الخمسةُ مجتازة\n";

/* ═══ علامةُ الماء ═══════════════════════════════════════════════════════ */
$stampFile = __DIR__ . '/2028_05_06_perm01_full_transition.watermark.json';
if (is_file($stampFile)) {
    $stamp = json_decode((string) file_get_contents($stampFile), true);
    if (!is_array($stamp) || !isset($stamp['max_profile'])) { $fail('علامةُ ماءٍ غيرُ مقروءة'); }
    echo "\nعلامةُ ماءٍ سابقةٌ محفوظة — تُقرأ ولا تُدهَس.\n";
} else {
    $stamp = array(
        'migration' => basename(__FILE__),
        'max_profile' => (int) $one('SELECT COALESCE(MAX(profile_id),0) FROM gov_role_profiles'),
        'max_item'    => (int) $one('SELECT COALESCE(MAX(item_id),0) FROM gov_profile_items'),
        'max_grant'   => (int) $one('SELECT COALESCE(MAX(grant_id),0) FROM gov_authority_grants'),
        'revoked' => array(), 'retired' => array(),
        'first_run_at' => gmdate('Y-m-d H:i:s') . ' UTC', 'runs' => 0,
    );
    $q = $conn->query("SELECT g.grant_id, g.user_id, g.reason FROM gov_authority_grants g
                         JOIN users u ON u.id = g.user_id AND {$LIVE}
                        WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    while ($x = $q->fetch_assoc()) { $stamp['revoked'][] = $x; }
    $q = $conn->query("SELECT profile_id FROM gov_role_profiles WHERE state = 'active'");
    while ($x = $q->fetch_row()) { $stamp['retired'][] = (int) $x[0]; }
    printf("\nعلامةُ ماءٍ جديدة: قوالب>%d · بنود>%d · منح>%d · منحٌ حيّةٌ تُنقل=%d · قوالبُ نافذةٌ تُتقاعَد=%d\n",
        $stamp['max_profile'], $stamp['max_item'], $stamp['max_grant'],
        count($stamp['revoked']), count($stamp['retired']));
}

/* ═══ ① عمودُ الدورِ في سجلِّ الأهداف ═════════════════════════════════════ */
echo "\n══ ① السندُ الثالثُ يُقصَر على دورِه ═════════════════════════════════\n";
$hasRole = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_target_item'
                          AND COLUMN_NAME='role_id'");
if ($hasRole === 0) {
    $conn->query("ALTER TABLE `perm01_target_item`
                    ADD COLUMN `role_id` INT NOT NULL DEFAULT 0
                        COMMENT 'صفر: بندُ مساحةٍ لكلِّ أدوارِها · وإلا بندُ رابطٍ لدورٍ بعينه'");
    $conn->query("ALTER TABLE `perm01_target_item` DROP INDEX `uq_target_item`");
    $conn->query("ALTER TABLE `perm01_target_item`
                    ADD UNIQUE KEY `uq_target_item` (`workspace_id`, `module_code`, `role_id`)");
    echo "   أُضيف role_id وأُعيد المفتاحُ الفريد\n";
}
/* يُعاد بناءُ بنودِ الرابطِ بالدورِ لا بالمساحة. */
$conn->query("DELETE FROM perm01_target_item WHERE origin = 'LIVE_LINK'");
$removed = $conn->affected_rows;
$conn->query("INSERT IGNORE INTO perm01_target_item (workspace_id, module_code, origin, source_ref, role_id)
              SELECT DISTINCT nr.workspace_id, m.code, 'LIVE_LINK',
                     CONCAT('live-link:nav_items للدور ', n.role_id), n.role_id
                FROM nav_items n
                JOIN nav_ws_roles nr ON nr.role_id = n.role_id
                JOIN modules m ON m.code = n.route
               WHERE n.active = 1
                 AND NOT EXISTS(SELECT 1 FROM perm01_target_item t
                                 WHERE t.workspace_id = nr.workspace_id
                                   AND t.module_code = m.code AND t.role_id = 0)");
printf("   بنودُ رابطٍ بالمساحةِ حُذفت: %d · وأُعيدت بالدور: %d\n", $removed, $conn->affected_rows);
$conn->query("UPDATE perm01_target_profile p SET p.screens_n =
                (SELECT COUNT(*) FROM perm01_target_item i WHERE i.workspace_id = p.workspace_id AND i.role_id = 0)");

/* ═══ ② قالبٌ لكلِّ دورٍ له حاملٌ حيّ ════════════════════════════════════ */
echo "\n══ ② قالبٌ واحدٌ لكلِّ دور ═══════════════════════════════════════════\n";
$roles = array();
$q = $conn->query("SELECT u.role rid, r.name rname, COUNT(*) n
                     FROM users u LEFT JOIN roles r ON r.id = u.role
                    WHERE {$LIVE} GROUP BY u.role, r.name ORDER BY u.role");
while ($x = $q->fetch_assoc()) { $roles[(int) $x['rid']] = $x; }
printf("   أدوارٌ لها حاملٌ حيّ: %d\n", count($roles));

$conn->query("UPDATE gov_policy_freeze SET active = 0, closed_at = NOW(), closed_by = 0,
                 reason = CONCAT(reason, ' | رفع مؤقت: PERM-01 §6 التحول الكامل')
               WHERE active = 1");
printf("   رُفع التجميدُ مؤقّتًا: %d مدًى\n\n", $conn->affected_rows);

$profOf = array();
foreach ($roles as $rid => $info) {
    $code = 'TGT-R' . $rid;
    $pid = (int) $one("SELECT profile_id FROM gov_role_profiles WHERE profile_code = '{$code}' LIMIT 1");
    if ($pid <= 0) {
        $title = mb_substr('هدف الدليل — ' . (string) $info['rname'], 0, 120);
        $rule = 'قالب واحد لكل دور ولا اتحاد — والدرجة لا تفرق في ورقة الدليل فالفرق على الافعال';
        $st = $conn->prepare(
            "INSERT INTO gov_role_profiles
               (company_id, profile_code, grade, dept_code, title_ar, screens_target,
                prepares_target, approves_target, approval_cap_label, data_scope,
                sensitive_fields, fixed_rule, version, state)
             VALUES (0, ?, 'G3', 'هدف الدليل', ?, 0, 0, 0, '', 'ادارته', '', ?, 1, 'draft')");
        $st->bind_param('sss', $code, $title, $rule);
        if (!$st->execute()) { $fail("تعذّر إصدارُ {$code}: " . $st->error); }
        $pid = (int) $conn->insert_id; $st->close();
    }
    $profOf[$rid] = $pid;

    /* بنودُ مساحتِه (role_id=0) + بنودُ رابطِه هو. */
    $conn->query("INSERT IGNORE INTO gov_profile_items
           (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
         SELECT 0, {$pid}, 'screen', t.module_code, 1, 0, 0, 0, 'perm01:target'
           FROM perm01_target_item t
           JOIN nav_ws_roles nr ON nr.workspace_id = t.workspace_id AND nr.role_id = {$rid}
          WHERE t.role_id IN (0, {$rid})");
    $n = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = {$pid}");
    $conn->query("UPDATE gov_role_profiles SET screens_target = {$n}, prepares_target = {$n}
                   WHERE profile_id = {$pid}");
    $conn->query("INSERT IGNORE INTO gov_profile_activation_approval
                    (profile_id, version, approved_by, reason, doc_ref)
                  VALUES ({$pid}, 1, 0, 'تحول كامل بامر PERM-01 §6 بقاعدة السند الحاكم', 'PERM-01-FULL')");
    $conn->query("UPDATE gov_role_profiles SET state = 'active' WHERE profile_id = {$pid}");
    $state = (string) $one("SELECT state FROM gov_role_profiles WHERE profile_id = {$pid}");
    printf("   %-9s دور %-3s %-26s شاشات=%-4s %s\n", $code, $rid,
        mb_substr((string) $info['rname'], 0, 24), $n, $state === 'active' ? '' : 'لم يُفعَّل');
    if ($state !== 'active' || $n < 1) { $fail("{$code} لم يُفعَّل أو فارغ"); }
}

/* ═══ ③ نقلُ كلِّ مستخدمٍ حيٍّ إلى قالبِ دورِه ═════════════════════════════ */
echo "\n══ ③ نقلُ المستخدمين ═════════════════════════════════════════════════\n";
$moved = 0; $newGrants = 0;
$q = $conn->query("SELECT u.id, u.role FROM users u WHERE {$LIVE} ORDER BY u.id");
$allUsers = array();
while ($x = $q->fetch_assoc()) { $allUsers[] = $x; }
foreach ($allUsers as $x) {
    $uid = (int) $x['id']; $rid = (int) $x['role'];
    if (!isset($profOf[$rid])) { continue; }
    $pid = $profOf[$rid];
    $has = (int) $one("SELECT COUNT(*) FROM gov_authority_grants
                        WHERE user_id = {$uid} AND profile_id = {$pid} AND revoked_at IS NULL");
    if ($has === 0) {
        $why = 'تحول كامل الى قالب الدور — PERM-01 §6 (قاعدة السند الحاكم)';
        $st = $conn->prepare("INSERT INTO gov_authority_grants
                (company_id, user_id, profile_id, source, valid_from, valid_to, issued_by, reason)
              VALUES (0, ?, ?, 'profile', NOW(), NULL, 0, ?)");
        $st->bind_param('iis', $uid, $pid, $why);
        if (!$st->execute()) { $fail("تعذّر إصدارُ منحةٍ للمستخدم {$uid}: " . $st->error); }
        $st->close(); $newGrants++;
    }
    /* وتُسحب منحُه القديمةُ كلُّها — فلا يجتمع قالبان في فاعل. */
    $conn->query("UPDATE gov_authority_grants SET revoked_at = NOW()
                   WHERE user_id = {$uid} AND profile_id <> {$pid} AND revoked_at IS NULL");
    $moved += $conn->affected_rows;
}
printf("   منحٌ جديدةٌ: %d · منحٌ قديمةٌ سُحبت: %d\n", $newGrants, $moved);

/* ═══ ④ تقاعدُ القوالبِ التي خلت ══════════════════════════════════════════ */
$conn->query("UPDATE gov_role_profiles p SET p.state = 'retired'
               WHERE p.state = 'active' AND p.profile_code NOT LIKE 'TGT-R%'
                 AND NOT EXISTS(SELECT 1 FROM gov_authority_grants g
                                 WHERE g.profile_id = p.profile_id AND g.revoked_at IS NULL
                                   AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
printf("   قوالبُ تقاعدت: %d\n", $conn->affected_rows);

$conn->query("UPDATE gov_policy_freeze SET active = 1, closed_at = NULL, closed_by = NULL WHERE active = 0");
printf("   أُعيد التجميد: %d مدًى\n", $conn->affected_rows);

/* ═══ ⑤ الشواهد ═════════════════════════════════════════════════════════ */
echo "\n══ ④ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
$cov = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE} AND EXISTS(
          SELECT 1 FROM gov_authority_grants g
            JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
           WHERE g.user_id = u.id AND g.revoked_at IS NULL
             AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
printf("   المغطَّون: %d من %d %s\n", $cov, $live, $cov === $live ? '' : 'ناقص');
if ($cov !== $live) { $good = false; }

$zero = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE} AND (
          SELECT COUNT(DISTINCT i.item_ref) FROM gov_authority_grants g
            JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
            JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen' AND i.allow = 1
           WHERE g.user_id = u.id AND g.revoked_at IS NULL
             AND (g.valid_to IS NULL OR g.valid_to > NOW())) = 0");
printf("   مستخدمٌ بصفرِ شاشة: %d %s\n", $zero, $zero === 0 ? '' : 'قفلٌ خارجَ النظام');
if ($zero !== 0) { $good = false; }

$multi = (int) $one("SELECT COUNT(*) FROM (
          SELECT g.user_id FROM gov_authority_grants g
            JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
            JOIN users u ON u.id = g.user_id AND {$LIVE}
           WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
           GROUP BY g.user_id HAVING COUNT(DISTINCT g.profile_id) > 1) z");
printf("   فاعلٌ بأكثرَ من قالبٍ نافذ: %d %s\n", $multi, $multi === 0 ? '' : 'اتحادٌ عاد');
if ($multi !== 0) { $good = false; }

$multiSrc = (int) $one("SELECT COUNT(*) FROM (
          SELECT i.profile_id FROM gov_profile_items i
            JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
           GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
printf("   قالبٌ نافذٌ مبذورٌ من أكثرَ من مصدر: %d %s\n", $multiSrc, $multiSrc === 0 ? '' : 'باقٍ');

$fzBack = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active = 1");
printf("   التجميدُ عائد: %d مدًى %s\n", $fzBack, $fzBack === 2 ? '' : 'لم يعُد');
if ($fzBack !== 2) { $good = false; }

$stamp['runs'] = (int) $stamp['runs'] + 1;
$stamp['last_run_at'] = gmdate('Y-m-d H:i:s') . ' UTC';
file_put_contents($stampFile, json_encode($stamp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — قالب واحد لكل دور، ولا فاعل بقالبين.\n" : "سقط شاهد — راجع اعلاه وشغل العكس\n");
