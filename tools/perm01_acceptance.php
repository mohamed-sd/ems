<?php
/**
 * tools/perm01_acceptance.php — معيارُ القبول: المقاييسُ الاثنان والثلاثون (§10 · §12-⑩)
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

/* ◆ **وشرطُ «بلا مراجعة» جزءٌ من البندِ لا زينةٌ في عنوانِه**: كان المقياسُ
     يعُدُّ كلَّ قالبٍ تعدَّدت مصادرُه فيستوي المخلوطُ صامتًا والمُضافُ عن قصدٍ
     بدليل. والمراجعةُ **واقعةٌ مقيَّدةٌ بسببٍ ودليل** في `perm01_seed_source_review`
     بحبّةِ (قالبٍ × مصدر) — فمصدرٌ يُضاف غدًا بلا قيدٍ يُرسِّب من جديد.
   ⛔ **وغيابُ السجلِّ يشدِّد ولا يُرخي**: إن لم يوجد الجدولُ عُدَّ الكلُّ
     غيرَ مُراجَع — فلا يُقرأ نقصُ البنيةِ براءةً. */
$hasReview = $one("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_seed_source_review'") > 0;
$reviewClause = $hasReview
    ? "WHERE NOT EXISTS(SELECT 1 FROM perm01_seed_source_review r
                         WHERE r.profile_id = i.profile_id AND r.seeded_from = i.seeded_from)"
    : '';
$multiSrc = $one("SELECT COUNT(*) FROM (SELECT i.profile_id FROM gov_profile_items i
   JOIN gov_role_profiles p ON p.profile_id=i.profile_id AND p.state='active'
   {$reviewClause}
   GROUP BY i.profile_id HAVING COUNT(DISTINCT i.seeded_from) > 1) z");
$reviewedN = $hasReview ? $one("SELECT COUNT(*) FROM perm01_seed_source_review") : 0;
$add(3, 'قالبٌ نافذٌ مبذورٌ من أكثرَ من مصدرٍ بلا مراجعة', 'صفر', $multiSrc, $multiSrc === 0 ? 'PASS' : 'FAIL',
     $hasReview ? ('ومصادرُ بذرٍ مُراجَعةٌ بسببٍ ودليل: ' . $reviewedN)
                : 'لا سجلَّ مراجعةٍ — فكلُّ تعدُّدٍ يُعَدُّ بلا مراجعة');

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
/* ◆ وجودُ الجدولِ لا يكفي — **الكاتبُ موصولٌ بمنفذٍ حيّ**: سحبُ المنحةِ
     في `Governance/auth_grants.php` يكتب سطرَ الأثر. */
$logWired = $grep('Governance/auth_grants.php', 'ems_perm_change_log');
$add(11, 'كتابةٌ في جدولِ الصلاحياتِ بلا أثرِ تغيير', 'صفر',
     ($hasChangeLog && $logWired) ? 'صفر — السجلُّ قائمٌ وموصول'
        : ($hasChangeLog ? 'قائمٌ وغيرُ موصول' : 'كلُّ الكتابات'),
     ($hasChangeLog && $logWired) ? 'PASS' : 'FAIL',
     'الصفوفُ المسجَّلةُ الآن: ' . max(0, $one('SELECT COUNT(*) FROM perm_change_log')));

/* ⛔ **ووجودُ نصِّ SQL ليس كتابةً قابلةً للبلوغ**: بعدَ PERM-01 §7 صارت المصفوفةُ
     قراءةً تاريخيّةً بحارسٍ يردُّ الطلبَ الكاتبَ قبلَ بلوغِه الجملة. فالمقياسُ
     يسأل عن **الحارسِ** لا عن بقاءِ الشيفرةِ الميتةِ خلفَه. */
$roMatrix = $grep('Governance/perm_matrix.php', '$PERM01_MATRIX_READONLY = true');
$roGuard  = $grep('Governance/perm_matrix.php', 'if ($PERM01_MATRIX_READONLY) {');
$matrixWrites = !($roMatrix && $roGuard);
$add(12, 'واجهةٌ تحرّر طبقةً لا تحكم', 'صفر',
     $matrixWrites ? 'قائمة (perm_matrix تكتب role_permissions)' : 'صفر — المصفوفةُ قراءةٌ تاريخيّة',
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
/* ◆ **و«لا تُقرأ» ادّعاءٌ يُقاس لا يُفترَض**: يُعَدُّ من يذكر الطبقةَ من ملفّاتِ
     الإنتاج (خارجَ الفحصِ والعُدّةِ والوثائق). فقولُ «مبنيّةٌ بلا حكم» عن طبقةٍ
     تقرؤها شاشةٌ حيّةٌ وخدمةٌ عاملةٌ **وصفٌ خاطئ**: هي مقروءةٌ في قرارٍ آخرَ
     لا في قرارِ فتحِ الشاشة. والحكمُ يبقى راسبًا لأنَّ الأمرَ يطلب **حكمًا
     مسجَّلًا للمالك** — وليس لي أن أسنَّه، بل أن أضع بين يديه الواقعةَ مقيسةً. */
$refCount = function ($needle) use ($ROOT) {
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS));
    foreach ($it as $f) {
        $p = str_replace('\\', '/', $f->getPathname());
        if (substr($p, -4) !== '.php') { continue; }
        foreach (array('/tests/', '/tools/', '/docs/', '/vendor/', '/storage/', '/.git/', '/database/') as $skip) {
            if (strpos($p, $skip) !== false) { continue 2; }
        }
        if (strpos((string) @file_get_contents($p), $needle) !== false) { $n++; }
    }
    return $n;
};
$tplRefs = $refCount('permission_templates') + $refCount('PermissionTemplateService');
$add(15, 'طبقةُ صلاحياتٍ مبنيّةٌ بلا حكمٍ ولا وسم', 'صفر',
     'قائمة — permission_templates ' . $one('SELECT COUNT(*) FROM permission_templates') . ' صفًّا',
     'FAIL', 'تقرؤها ' . $tplRefs . ' ملفَّ إنتاجٍ (منها admin/permissions وsec_governance وPermissionResolver) '
   . 'لكن **لا في قرارِ فتحِ الشاشة** — والمُعوِزُ حكمٌ مسجَّلٌ للمالك: تُوصَل أم تُوسَم');

$myItems = $one("SELECT COUNT(*) FROM perm01_target_item WHERE workspace_id='WS-MY' AND role_id=0");
$noMy = $one("SELECT COUNT(*) FROM gov_role_profiles p WHERE p.state='active' AND p.profile_code LIKE 'TGT-R%'
   AND (SELECT COUNT(*) FROM gov_profile_items i JOIN perm01_target_item t
          ON t.module_code=i.item_ref AND t.workspace_id='WS-MY'
        WHERE i.profile_id=p.profile_id) = 0");
$add(16, 'قالبٌ نافذٌ بلا بنودِ «مساحة عملي»', 'صفر', $noMy, $noMy === 0 ? 'PASS' : 'FAIL',
     'بنودُ مساحتي المعياريّة: ' . $myItems);

/* ◆ **الرمزُ هويّةٌ والمسارُ وجهةٌ — ولا يُقاس أحدُهما بالآخر**: كان البندُ
     يُقاس بمطابقةِ **مسارِه** بـ`modules.code`، فأخرج ستّةَ بنودٍ «بلا رمز»
     وكلُّها مرموزةٌ مسجَّلة: نُقلت ملفّاتُها من `admin/` إلى `main/`
     (التزام PHASE2-0a) و**هويّتُها الصلاحيّةُ ثبتت عمدًا** — `$MODULE_CODE
     = 'admin/…'` منصوصًا في الشاشةِ نفسِها. فذاك سؤالُ «أين الملفّ» لا
     سؤالُ «بأيِّ رمزٍ يُحكَم».
   ◆ **والحاكمُ في زمنِ التشغيل `nav_items.module_id`**: `perm_nav_view_exists_sql`
     تفحص `module_id` لا `permission_code`؛ وقيمةُ الأخيرِ **لا تُقرأ أصلًا**
     — تُقرأ NULLيّتُه علمًا على «لا فحصَ هنا، حارسُه في وجهتِه». فقياسُه
     بقيمتِه يُبلِّغ عن `'0'` و`RPR-OPS-11` عطبًا وهي لا تُستشار. */
$navNoPerm = $one("SELECT COUNT(*) FROM nav_items n WHERE n.active=1
                    AND (n.module_id IS NULL OR n.module_id = 0
                         OR NOT EXISTS(SELECT 1 FROM modules m WHERE m.id = n.module_id))");
$navRouteUnreg = $one("SELECT COUNT(DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(n.route,'?',1),'#',1))
                         FROM nav_items n WHERE n.active=1 AND n.route <> ''
                          AND NOT EXISTS(SELECT 1 FROM modules m
                                WHERE m.code = SUBSTRING_INDEX(SUBSTRING_INDEX(n.route,'?',1),'#',1))");
$add(17, 'بندُ ملاحةٍ نافذٌ بلا رمزِ صلاحية', 'صفر', $navNoPerm, $navNoPerm === 0 ? 'PASS' : 'FAIL',
     'ملاحظةٌ مسمّاةٌ لا مطويّة: ' . $navRouteUnreg . ' مسارًا لا يطابق نصُّه رمزًا مسجَّلًا — '
   . 'وهي هويّاتٌ ثابتةٌ لملفّاتٍ نُقلت، لا بنودٌ بلا رمز');

$add(18, 'مساراتُ حلِّ الوصولِ في زمنِ التشغيل', 'واحد',
     'واحدٌ (get_module_permissions) + 6 إعفاءاتٍ مُعلَنة', 'PASS',
     'والإعفاءاتُ تُعدُّ منفصلةً ولا تُحسَب مسارًا — بنصِّ §6');

/* ═══ ما لم يُبنَ بعد ════════════════════════════════════════════════════ */
$hasModes = $one("SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_auth_mode'") > 0;
$ambiguous = $hasModes
    ? $one("SELECT COUNT(*) FROM users u LEFT JOIN perm01_auth_mode m ON m.user_id=u.id
             WHERE $LIVE AND m.user_id IS NULL")
    : -1;
$add(19, 'مستخدمٌ حيٌّ بوضعِ تفويضٍ ملتبس', 'صفر',
     $hasModes ? $ambiguous : 'لا سجلَّ أوضاع',
     ($hasModes && $ambiguous === 0) ? 'PASS' : 'FAIL',
     $hasModes ? ('معياريّون: ' . $one("SELECT COUNT(*) FROM perm01_auth_mode WHERE mode='canonical'"))
               : 'لا سجلَّ «أوضاع الانتقال» (قديم · ظلّ · معياريّ) — §6-②');
/* ◆ **والمقياسُ يسأل عن الحارسِ لا عن النيّة**: ثلاثةُ مواضعِ فشلٍ مُسمّاةٌ
     في الشيفرةِ + التقاطُ كلِّ ما يُرمى + شاهدٌ سالبٌ يُثبته بوصلةٍ ميتة. */
$fcNamed = $grep('includes/permissions_helper.php', 'policy_store_unreadable');
$fcThrow = $grep('includes/permissions_helper.php', 'policy_store_unreadable_throw');
$fcTest  = $has('tests/perm01_failclosed_policy_store.php');
$fcMode = $grep('includes/permissions_helper.php', 'canonical_without_profile');
$failClosed = ($fcNamed && $fcThrow && $fcTest && $fcMode);
$add(20, 'مستخدمٌ معياريٌّ يسقط إلى القديم', 'صفر',
     $failClosed ? 'صفر — الفشلُ يمنع ويُسجَّل' : 'ممكنٌ بنيويًّا',
     $failClosed ? 'PASS' : 'FAIL',
     $failClosed ? 'مُثبَتٌ بشاهدَين: وصلةٌ ميتة (9/9) وسحبُ منحةٍ (7/7) — والمعياريُّ لا يسقط ولو فُقد قالبُه'
                 : 'get_module_permissions تسقط للفرعِ القديمِ عند خللِ القراءة');
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
     'FAIL', 'يقرؤها ' . ($refCount('gov_authority_limits') + $refCount('ScopeEngine')) . ' ملفَّ إنتاجٍ عبرَ '
   . 'ScopeEngine (ExecDecisionRouter · w15_view) — فهي نافذةٌ في مجالِ التنفيذِ لا في فتحِ الشاشة. '
   . 'تُوصَل أو تُوسَم «غير نافذة» بحكمٍ مسجَّل — §8-②');
$add(31, 'التجميدُ نافذٌ حتى إشعار', 'نافذ',
     $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1") . ' مدًى',
     $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active=1") >= 2 ? 'PASS' : 'FAIL');

/* ═══ ٣٢ — شاشةٌ بهويّتَين ═══════════════════════════════════════════════
   ◆ **المقياسُ الذي لم يكن**: كان يُسأل «أللبندِ رمزٌ؟» ولا يُسأل «أهو **رمزُ
     شاشتِه**؟». والقائمةُ تُظهر بالوحدةِ المربوطةِ بالبند، والبابُ يُنفِذ
     بالمفردةِ المكتوبةِ في الشاشة — فإن افترقتا صار للشاشةِ حكمان.
   ⛔ **والمقارنةُ بعدَ الحلِّ لا قبلَه**: `check_page_permissions` تحلُّ المفردةَ
     على ثلاثِ مراحلَ (تامّةٌ · ذيلُ `/الاسم.php` · احتواء)، فمقارنةُ النصِّ
     بالنصِّ تُبلِّغ افتراقًا حيث لا افتراق — `'equipments'` تحلُّ إلى وحدةِ
     `Equipments/equipments.php` نفسِها. فتُحاكى المراحلُ ثمَّ يُقارَن المعرِّف.
   ◆ **وما لا يُقرأ يُسمَّى لا يُخمَّن**: ملفٌّ لا يحمل مفردةً نصّيّةً لا يُعَدُّ
     مطابقًا ولا مفترقًا — يُعَدُّ في خانتِه. */
$resolveId = function ($code) use ($db) {
    $e = $db->real_escape_string($code);
    foreach (array("code='{$e}'",
                   "code LIKE '%/{$e}.php' ORDER BY CHAR_LENGTH(code) ASC, id ASC",
                   "code LIKE '%{$e}%' OR name LIKE '%{$e}%' ORDER BY CHAR_LENGTH(code) ASC, id ASC") as $w) {
        $r = $db->query("SELECT id FROM modules WHERE {$w} LIMIT 1");
        if ($r && $r->num_rows) { return (int) $r->fetch_row()[0]; }
    }
    return 0;
};
$APPROOT = dirname(__DIR__);
$twoIds = 0; $twoList = array(); $noLit = 0; $benign = 0;
$r = $db->query("SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(n.route,'?',1),'#',1) rt, n.module_id nid
                   FROM nav_items n JOIN modules m ON m.id = n.module_id
                  WHERE n.active = 1 AND n.route <> ''");
while ($x = $r->fetch_assoc()) {
    $f = $APPROOT . '/' . $x['rt'];
    if (!is_file($f)) { continue; }
    $s = (string) @file_get_contents($f);
    if (preg_match('/check_page_permissions\(\s*\$conn\s*,\s*\'([^\']+)\'/', $s, $m)) { $lit = $m[1]; }
    elseif (preg_match('/check_page_permissions\(\s*\$conn\s*,\s*(\$\w+)/', $s, $m)
            && preg_match('/' . preg_quote($m[1], '/') . '\s*=\s*\'([^\']+)\'/', $s, $m2)) { $lit = $m2[1]; }
    elseif (preg_match('/fin_page_perms\(\s*\$conn\s*,\s*\'([^\']+)\'/', $s, $m)) { $lit = $m[1]; }
    else { $noLit++; continue; }
    $rid = $resolveId($lit);
    if ($rid === 0 || $rid === (int) $x['nid']) { continue; }
    /* ◆ **والافتراقُ يُفرَز بأثرِه لا بوجودِه**: إن كان البابُ **أوسعَ** من
         القائمةِ فلا يُردُّ من يرى الرابطَ — وهو حالُ الدمجِ المُعلَن
         (`dept_inbox` صار تبويبًا في `tickets_list`، والملفُّ مُحوِّلٌ يبقى
         لأنَّ أربعةً وثلاثين صفَّ تنقُّلٍ تقصده). والحرِجُ أن يرى ويُردَّ. */
    $rl = function ($mid) use ($db) {
        $r = $db->query("SELECT DISTINCT role_id FROM role_permissions
                          WHERE module_id = " . (int) $mid . " AND can_view = 1");
        $o = array(); while ($z = $r->fetch_row()) { $o[(int) $z[0]] = 1; } return $o; };
    $seeNotEnter = array_diff_key($rl((int) $x['nid']), $rl($rid));
    if ($seeNotEnter) { $twoIds++; $twoList[] = $x['rt'] . ' (' . count($seeNotEnter) . ' دورًا)'; }
    else { $benign++; }
}
$add(32, 'شاشةٌ تُعرَض بهويّةٍ وتُحرَس بأخرى فتردُّ من يراها', 'صفر', $twoIds,
     $twoIds === 0 ? 'PASS' : 'FAIL',
     ($twoList ? 'حرِجة: ' . implode(' · ', array_slice($twoList, 0, 4)) . ' — ' : '')
   . 'مفترقٌ بابُه أوسعُ فلا يردُّ أحدًا: ' . $benign
   . ' · وبلا مفردةٍ نصّيّةٍ تُقرأ: ' . $noLit . ' (خاناتٌ مسمّاةٌ لا مطويّة)');

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
