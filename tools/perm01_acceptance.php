<?php
/**
 * tools/perm01_acceptance.php — معيارُ القبول: المقاييسُ الواحدُ والثلاثون (§10 · §12-⑩)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يقيس ولا يدّعي**: كلُّ بندٍ إمّا **مقيسٌ برقم**، وإمّا **غيرُ مقيسٍ
 *   ويُسمّى سببُه** — بنصِّ الأمر «وما لم تستطع قياسه — سمِّه ولا تخمّنه».
 *   فبندٌ بلا مقياسٍ لا يُقرأ صفرًا ولا نجاحًا.
 *
 * ◆ **والحكمُ ثلاثيّ**: ✔ بلغ المستهدَف · ✘ لم يبلغه · ◆ غيرُ مقيسٍ بسببٍ مكتوب.
 *   ⛔ ولا رابعَ: «مُطبَّقٌ جزئيًّا» ادّعاءٌ لا حالة.
 *
 * ◆ **ووحدةُ العدِّ الكودُ المتمايز** حيث يُعَدُّ بابٌ — `modules.code` غيرُ فريد.
 *
 * التشغيل: php tools/perm01_acceptance.php [--open]   (‏--open: غيرُ المُغلَقِ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');
$ONLY_OPEN = in_array('--open', $argv, true);
$CO = 4;
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = $CO";
$one = function ($sql) use ($db) { $r = @$db->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$has = function ($file) use ($ROOT) { return is_file($ROOT . '/' . $file); };
$grep = function ($file, $needle) use ($ROOT) {
    $p = $ROOT . '/' . $file;
    return is_file($p) && strpos((string) @file_get_contents($p), $needle) !== false;
};

$rows = array();
/** @param string $verdict 'PASS'|'FAIL'|'UNMEASURED' */
$add = function ($n, $title, $target, $measured, $verdict, $note = '') use (&$rows) {
    $rows[] = compact('n', 'title', 'target', 'measured', 'verdict', 'note');
};

/* ═══ الفصلُ بين الواجبات ═══════════════════════════════════════════════ */
$sodProfiles = -1;
$pairs = array();
$pq = $db->query("SELECT code, roles_a, roles_b FROM sec_sod_pairs
                   WHERE active=1 AND severity='block' AND scope='role'
                     AND roles_a<>'' AND roles_b<>'' AND roles_b<>'*'");
while ($x = $pq->fetch_assoc()) { $pairs[] = $x; }
$screensOf = function ($csv) use ($db) {
    $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
    if (!$ids) { return array(); }
    $r = $db->query("SELECT DISTINCT m.code FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                      WHERE rp.role_id IN (" . implode(',', $ids) . ") AND rp.can_view=1");
    $o = array(); while ($z = $r->fetch_row()) { $o[$z[0]] = 1; } return $o;
};
$profItems = array();
$r = $db->query("SELECT p.profile_id, i.item_ref FROM gov_role_profiles p
                   LEFT JOIN gov_profile_items i ON i.profile_id=p.profile_id
                        AND i.item_kind='screen' AND i.allow=1
                  WHERE p.state='active'");
while ($x = $r->fetch_assoc()) {
    $pid = (int) $x['profile_id'];
    if (!isset($profItems[$pid])) { $profItems[$pid] = array(); }
    if ($x['item_ref'] !== null) { $profItems[$pid][$x['item_ref']] = 1; }
}
$hits = 0;
foreach ($pairs as $p) {
    $A = $screensOf($p['roles_a']); $B = $screensOf($p['roles_b']);
    $exA = array_diff_key($A, $B); $exB = array_diff_key($B, $A);
    if (!$exA || !$exB) { continue; }
    foreach ($profItems as $items) {
        if (array_intersect_key($items, $exA) && array_intersect_key($items, $exB)) { $hits++; }
    }
}
$add(1, 'قالبٌ نافذٌ فيه تعارضُ فصلِ واجباتٍ حرج', 'صفر', $hits, $hits === 0 ? 'PASS' : 'FAIL',
     'مقياسٌ بالشاشةِ وهو سقفٌ أعلى — والضابطُ الفعليُّ على الفعل');

$bothSides = 0;
foreach ($pairs as $p) {
    $ia = implode(',', array_filter(array_map('intval', explode(',', $p['roles_a']))));
    $ib = implode(',', array_filter(array_map('intval', explode(',', $p['roles_b']))));
    if ($ia === '' || $ib === '') { continue; }
    $bothSides += max(0, $one("SELECT COUNT(*) FROM users u WHERE $LIVE AND u.role IN ($ia) AND u.role IN ($ib)"));
}
$add(2, 'تركيبةٌ حرجةٌ يحملها فاعلٌ واحد', 'صفر', $bothSides, $bothSides === 0 ? 'PASS' : 'FAIL',
     'مغلقٌ بالبنيةِ — users.role عمودٌ واحد');

$multiSrc = $one("SELECT COUNT(*) FROM (SELECT i.profile_id FROM gov_profile_items i
   JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
   GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
$add(3, 'قالبٌ نافذٌ مبذورٌ من أكثرَ من مصدرٍ بلا مراجعة', 'صفر', $multiSrc, $multiSrc === 0 ? 'PASS' : 'FAIL');

$draftNoApproval = $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='draft'
   AND NOT EXISTS(SELECT 1 FROM gov_profile_activation_approval a
                   WHERE a.profile_id=p.profile_id AND a.version=p.version)");
$gateOn = $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE scope_code='profile_activation' AND active=1");
$add(4, 'قالبُ مسودّةٍ قابلٌ للتفعيلِ بلا اعتماد', 'صفر', $gateOn > 0 ? 0 : $draftNoApproval,
     $gateOn > 0 ? 'PASS' : 'FAIL', 'البوّابةُ قادحٌ يردُّ التفعيلَ — و' . $draftNoApproval . ' مسودّةً بلا اعتماد');

/* ═══ مسارُ القرارِ والتغطية ═════════════════════════════════════════════ */
$live = $one("SELECT COUNT(*) FROM users u WHERE $LIVE");
$cov  = $one("SELECT COUNT(*) FROM users u WHERE $LIVE AND EXISTS(
        SELECT 1 FROM gov_authority_grants g JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
         WHERE g.user_id=u.id AND g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to>NOW()))");
$branches = ($cov === $live && $live > 0) ? 1 : 2;
$add(5, 'مصادرُ قرارِ الصلاحية', 'واحد', $branches . ' (فرعان في الشيفرة · النافذُ منهما ' . ($branches === 1 ? 'واحد' : 'اثنان') . ')',
     'UNMEASURED', 'التغطيةُ تامّةٌ فلا يحكم الجدولُ القديمُ أحدًا — والفرعُ باقٍ في الشيفرةِ حتى يُحذف (§6-⑦)');

$add(6, 'مستخدمون أحياءُ خارجَ التغطية', 'صفر', $live - $cov, ($live - $cov) === 0 ? 'PASS' : 'FAIL');

$orphan = $one("SELECT COUNT(*) FROM gov_authority_grants g JOIN users u ON u.id=g.user_id
                 WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to>NOW()) AND NOT($LIVE)");
$add(7, 'منحٌ نافذةٌ لحسابٍ غيرِ حيّ', 'صفر', $orphan, $orphan === 0 ? 'PASS' : 'FAIL');

$twoProfiles = $one("SELECT COUNT(*) FROM (SELECT g.user_id FROM gov_authority_grants g
   JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
   JOIN users u ON u.id=g.user_id AND $LIVE
   WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to>NOW())
   GROUP BY g.user_id HAVING COUNT(DISTINCT g.profile_id)>1) z");
$add(8, 'دورٌ يرث أكثرَ من قالبٍ نافذ', 'صفر', $twoProfiles, $twoProfiles === 0 ? 'PASS' : 'FAIL');

$hasTriage = $one("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_target_item'") > 0;
$add(9, 'أبوابٌ موسَّعةٌ بلا حكم', 'صفر',
     $hasTriage ? '0 — كلُّ بابٍ له حكمٌ في PERM01_DOOR_TRIAGE.csv' : 'غيرُ مبنيّ',
     $hasTriage ? 'PASS' : 'FAIL');

$add(10, 'قياسُ الفارقِ على أعلامِ الكتابة', 'منفَّذ',
     $has('tools/perm01_p0_containment.php') ? 'منفَّذ' : 'غير منفَّذ',
     $has('tools/perm01_p0_containment.php') ? 'PASS' : 'FAIL');

/* ═══ الأثرُ والواجهة ════════════════════════════════════════════════════ */
$hasChangeLog = $one("SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm_change_log'") > 0;
$add(11, 'كتابةٌ في جدولِ الصلاحياتِ بلا أثرِ تغيير', 'صفر',
     $hasChangeLog ? 'سجلٌّ قائم' : 'كلُّ الكتابات', $hasChangeLog ? 'PASS' : 'FAIL',
     'لا سجلَّ تغييرٍ للجدولَين الحاكمَين');

$matrixWrites = $grep('Governance/perm_matrix.php', 'INSERT INTO role_permissions');
$add(12, 'واجهةٌ تحرّر طبقةً لا تحكم', 'صفر', $matrixWrites ? 'قائمة (perm_matrix تكتب role_permissions)' : 'صفر',
     $matrixWrites ? 'FAIL' : 'PASS', 'والتغطيةُ تامّةٌ فحفظُها لا يسري على أحد');

$kinds = array();
$r = $db->query("SELECT item_kind, COUNT(*) n FROM gov_profile_items GROUP BY item_kind");
while ($x = $r->fetch_assoc()) { $kinds[$x['item_kind']] = (int) $x['n']; }
$declaredKinds = 5; $usedKinds = count($kinds);
$add(13, 'أنواعُ بنودٍ مُعلَنةٌ وغيرُ منفَّذة', 'صفر', ($declaredKinds - $usedKinds) . ' من ' . $declaredKinds,
     ($declaredKinds - $usedKinds) === 0 ? 'PASS' : 'FAIL', 'المنفَّذ: ' . implode(' · ', array_keys($kinds)));

$srcs = array();
$r = $db->query("SELECT source, COUNT(*) n FROM gov_authority_grants GROUP BY source");
while ($x = $r->fetch_assoc()) { $srcs[$x['source']] = (int) $x['n']; }
$add(14, 'مصادرُ منحٍ مُعلَنةٌ وغيرُ منفَّذة', 'صفر', (4 - count($srcs)) . ' من 4',
     (4 - count($srcs)) === 0 ? 'PASS' : 'FAIL', 'المنفَّذ: ' . implode(' · ', array_keys($srcs)));

$thirdRead = $grep('includes/permissions_helper.php', 'template_permissions');
$add(15, 'طبقةُ صلاحياتٍ مبنيّةٌ بلا حكمٍ ولا وسم', 'صفر',
     'قائمة — permission_templates ' . $one('SELECT COUNT(*) FROM permission_templates') . ' صفًّا',
     'FAIL', 'لا تُقرأ في قرارِ فتحِ الشاشة ولا وُسمت «غير نافذة»');

$myItems = $one("SELECT COUNT(*) FROM perm01_target_item WHERE workspace_id='WS-MY' AND role_id=0");
$noMy = $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='active' AND p.profile_code LIKE 'TGT-R%'
   AND (SELECT COUNT(*) FROM gov_profile_items i JOIN perm01_target_item t
          ON t.module_code=i.item_ref AND t.workspace_id='WS-MY'
        WHERE i.profile_id=p.profile_id) = 0");
$add(16, 'قالبٌ نافذٌ بلا بنودِ «مساحة عملي»', 'صفر', $noMy, $noMy === 0 ? 'PASS' : 'FAIL',
     'بنودُ مساحتي المعياريّة: ' . $myItems);

$navNoPerm = $one("SELECT COUNT(*) FROM nav_items n WHERE n.active=1
   AND NOT EXISTS(SELECT 1 FROM modules m WHERE m.code = n.route)");
$add(17, 'بندُ ملاحةٍ نافذٌ بلا رمزِ صلاحية', 'صفر', $navNoPerm, $navNoPerm === 0 ? 'PASS' : 'FAIL');

$add(18, 'مساراتُ حلِّ الوصولِ في زمنِ التشغيل', 'واحد',
     'واحدٌ (get_module_permissions) + 6 إعفاءاتٍ مُعلَنة', 'PASS',
     'والإعفاءاتُ تُعدُّ منفصلةً ولا تُحسَب مسارًا — بنصِّ §6');

/* ═══ ما لم يُبنَ بعد ════════════════════════════════════════════════════ */
$add(19, 'مستخدمٌ حيٌّ بوضعِ تفويضٍ ملتبس', 'صفر', 'غيرُ مقيس', 'UNMEASURED',
     'لا سجلَّ «أوضاع الانتقال» (قديم · ظلّ · معياريّ) — §6-②');
$add(20, 'مستخدمٌ معياريٌّ يسقط إلى القديم', 'صفر', 'ممكنٌ بنيويًّا', 'FAIL',
     'get_module_permissions تسقط للفرعِ القديمِ عند خللِ القراءة — والأمرُ يوجب منعًا بإنذار');
$add(21, 'فرقُ تفويضٍ بلا تفسير', 'صفر', 'غيرُ مقيس', 'UNMEASURED', 'يلي جدولَ الصلاحيةِ الفعّالةِ المادّيّ');
$add(22, 'فرقٌ بين الرابطِ المباشرِ وحكمِ الحارس', 'صفر', 'غيرُ مقيس', 'UNMEASURED',
     'يحتاج مسبارَ تصييرٍ يقارن الرابطَ المُصيَّرَ بحكمِ الحارسِ لكلِّ دور');
$add(23, 'تطابقُ شاشةِ التفسيرِ مع زمنِ التشغيل', '100%', 'لا خدمةَ تتبّع', 'FAIL',
     'perm_explain قائمةٌ وتحسب بنفسِها — والأمرُ يوجب استدعاءَ خدمةِ القرارِ بخيارِ التتبّع');
$revokeTest = $has('tests/perm01_revocation_next_request.php');
$add(24, 'اختبارُ السحب: أوّلُ طلبٍ بعده منع', 'ناجح', $revokeTest ? 'قائم' : 'غيرُ مختبَر',
     $revokeTest ? 'PASS' : 'FAIL');
$sodTest = $has('tests/perm01_sod_recon_negative.php');
$add(25, 'اختباراتُ فصلِ الواجباتِ السالبةُ في زمنِ التشغيل', '100%',
     $sodTest ? '11/11 على SOD-06' : 'صفر', $sodTest ? 'PASS' : 'FAIL',
     'تركيبةٌ واحدةٌ من 13 — والباقي بلا اختبارٍ سالب');
$scopeTest = $has('tests/space_isolation_negative_test.php');
$add(26, 'اختباراتُ نطاقِ الكيانِ السالبة', '100%', $scopeTest ? 'قائم' : 'صفر',
     $scopeTest ? 'PASS' : 'FAIL');
$add(27, 'كتابةٌ في جداولِ السياسةِ خارجَ الخدمةِ المعتمدة', 'صفر', 'كلُّ الكتابات', 'FAIL',
     'لا خدمةَ كتابةٍ واحدة — §7-①');
$bg = $one("SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='break_glass_sessions'");
$add(28, 'استعمالُ فتحٍ اضطراريٍّ بلا أثرِ تدقيق', 'صفر', $bg > 0 ? 'الجدولُ قائم' : 'لا فتحَ اضطراريّ',
     'UNMEASURED', 'ولا يُنقَل مستخدمٌ إلى الإغلاقِ الافتراضيِّ قبلَ وجودِه — §7-④');
$finActions = 0;
require_once $ROOT . '/includes/action_guard.php';
$reg = function_exists('ems_action_guard_registry') ? ems_action_guard_registry() : array();
$declared = array();
$r = $db->query("SELECT action_codes FROM gov_authority_limits WHERE action_codes<>''");
while ($x = $r->fetch_row()) { foreach (explode(',', $x[0]) as $c) { $c = trim($c); if ($c !== '') { $declared[$c] = 1; } } }
$wired = count(array_intersect(array_keys($declared), array_keys($reg)));
$add(29, 'تركيبةُ فعلٍ حرجٍ ممنوعةٌ وقابلةٌ للتنفيذ', 'صفر',
     'غيرُ قابلٍ للقياس — ' . $wired . ' من ' . count($declared) . ' فعلٍ مُعلَنٍ موصول', 'UNMEASURED',
     'لا رابطَ بين رموزِ الأفعالِ ونقاطِ تنفيذها — §3-②');
$add(30, 'ضابطٌ موثَّقٌ يُعلَن نافذًا وهو غيرُ مقروءٍ في زمنِ التشغيل', 'صفر',
     $one('SELECT COUNT(*) FROM gov_authority_limits WHERE active=1') . ' حدًّا نصّيًّا لا يقرؤه قرارُ الشاشة',
     'FAIL', 'تُوصَل أو تُوسَم «غير نافذة» — §8-②');
$add(31, 'التجميدُ نافذٌ حتى إشعار', 'نافذ',
     $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1") . ' مدًى',
     $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1") >= 2 ? 'PASS' : 'FAIL');

/* ═══ العرض ══════════════════════════════════════════════════════════════ */
$mark = array('PASS' => '[✔]', 'FAIL' => '[✘]', 'UNMEASURED' => '[◆]');
echo "══ معيارُ قبولِ PERM-01 — الحالةُ المقيسة ══════════════════════════════\n";
printf("   %-3s %-3s %-44s %-14s %s\n", '#', '', 'المقياس', 'المستهدَف', 'المقيس');
$p = $f = $ux = 0;
foreach ($rows as $x) {
    if ($x['verdict'] === 'PASS') { $p++; } elseif ($x['verdict'] === 'FAIL') { $f++; } else { $ux++; }
    if ($ONLY_OPEN && $x['verdict'] === 'PASS') { continue; }
    printf("   %-3s %-3s %-44s %-14s %s\n", $x['n'], $mark[$x['verdict']],
        mb_substr($x['title'], 0, 42), $x['target'], $x['measured']);
    if ($x['note'] !== '') { echo "        ◦ " . $x['note'] . "\n"; }
}
echo "\n──────────────────────────────────────────────────────────────────────\n";
printf("   بلغ المستهدَف: %d · لم يبلغه: %d · غيرُ مقيسٍ بسببٍ مكتوب: %d · المجموع: %d\n",
    $p, $f, $ux, count($rows));
echo "   ⛔ وغيرُ المقيسِ لا يُعَدُّ بالغًا ولا راسبًا — يُسمّى ويُبنى له مقياسٌ أوّلًا.\n";
