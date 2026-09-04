<?php
/**
 * tools/perm01_p0_containment.php — PERM-01 · قياسُ احتواءِ الخرقِ (المسار الأول)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا يُخترع سجلٌّ موجود**: أمرُ PERM-01 §4 يطلب «سجلَّ تركيباتِ فصلِ
 *   الواجبات» — وهو قائمٌ في `sec_sod_pairs` (13 تركيبة) و`sod_conflicts`
 *   (10 بالأذونات)، ومصدرُه الحاكمُ `gov_authority_limits` (55 قيدًا بأوراقِ
 *   FIN-ACC-01 · FIN-CTRL-01 · FIN-MGR-01 · FIN-TRE-01 · IAF-01). فهذه الأداةُ
 *   **تقرأ الحاكمَ ولا تؤلّف** — والتأليفُ هنا كان سيصنع مصدرًا ثانيًا يتفرّق.
 *
 * ◆ **والخرقُ على القالبِ لا على الشخصِ وحدَه**: `sec_sod_pairs` نطاقُها `role`
 *   فتمنع اجتماعَ دورَين في فاعل. لكنّ قالبًا واحدًا يمنح **شاشاتِ الطرفَين**
 *   لحاملِ أحدِهما — فيبلغ وظيفةَ الطرفِ الآخرِ بلا أن يحمل دورَه. وهذا ما
 *   يقيسه `ACTIVE_PROFILE_WITH_P0_SOD`.
 *
 * ◆ **تعريفُ القياسِ مُعلَنٌ لا مضمَر**: شاشاتُ الطرفِ = ما يمنحه
 *   `role_permissions` لأدوارِ ذلك الطرف؛ و**الحصريّةُ** = ما لا يشترك فيه
 *   الطرفان (فالمشتركُ ليس دليلَ تعارض). والقالبُ متعارضٌ متى حمل حصريَّ
 *   الطرفَين معًا. والوحدةُ **الكودُ المتمايز** لا صفُّ الوحدة.
 *
 * ⛔ **وصفرٌ لا يُقرأ سلامةً بلا شاهدٍ صناعيّ**: الأداةُ تفحص أنَّ الكاشفَ يرى
 *   تعارضًا معلومًا قبلَ أن يُقرأ صفرُه براءة.
 *
 * التشغيل: php tools/perm01_p0_containment.php [--company=4] [--write]
 *          `--write` يُقيِّد الكشفَ في `gov_sod_conflict` (عاطلةٌ بوسمِ المصدر)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

$CO = 4;
$WRITE = in_array('--write', $argv, true);
foreach ($argv as $a) { if (preg_match('/^--company=(\d+)$/', $a, $m)) { $CO = (int) $m[1]; } }
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = $CO";
$one  = function ($sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$col  = function ($sql) use ($db) { $r = $db->query($sql); $o = array(); if ($r) { while ($x = $r->fetch_row()) { $o[] = $x[0]; } } return $o; };

/* ═══ ① السجلُّ الحاكم — يُقرأ ولا يُؤلَّف ═════════════════════════════ */
echo "══ ① سجلُّ التركيبات — قائمٌ سلفًا ولا يُخترع ══════════════════════════\n";
printf("   sec_sod_pairs (تركيبات)            %d  · منها نافذةٌ بشدّةِ منع: %d\n",
    $one('SELECT COUNT(*) FROM sec_sod_pairs'),
    $one("SELECT COUNT(*) FROM sec_sod_pairs WHERE active=1 AND severity='block'"));
printf("   منها نطاقُها `role` (تُقاس هنا)     %d\n",
    $one("SELECT COUNT(*) FROM sec_sod_pairs WHERE active=1 AND severity='block' AND scope='role' AND roles_a<>'' AND roles_b<>''"));
printf("   sod_conflicts (بالأذونات)          %d\n", $one('SELECT COUNT(*) FROM sod_conflicts'));
printf("   gov_authority_limits (المصدرُ الحاكم) %d · أوراقٌ: %s\n",
    $one('SELECT COUNT(*) FROM gov_authority_limits'),
    implode(' · ', $col('SELECT DISTINCT doc_code FROM gov_authority_limits ORDER BY doc_code')));
printf("   gov_sod_conflict (سجلُّ الكشف)      %d  %s\n",
    $one('SELECT COUNT(*) FROM gov_sod_conflict'),
    $one('SELECT COUNT(*) FROM gov_sod_conflict') === 0 ? '⛔ فارغٌ — لا كاشفَ يكتب فيه' : '');
printf("   sec_sod_denials (سجلُّ المنع)       %d  %s\n",
    $one('SELECT COUNT(*) FROM sec_sod_denials'),
    $one('SELECT COUNT(*) FROM sec_sod_denials') === 0 ? '⛔ فارغٌ — لا منعَ مقيَّد' : '');

/* ═══ ② الكاشفُ على القوالبِ النافذة ═══════════════════════════════════ */
echo "\n══ ② الكاشفُ — أيجمع قالبٌ نافذٌ حصريَّ طرفَي تركيبةٍ حرجة؟ ════════════\n";
echo "   التعريف: حصريُّ الطرف = شاشاتُ أدوارِه في role_permissions ناقصَ المشتركَ مع الطرفِ الآخر.\n\n";

$pairs = array();
$pq = $db->query("SELECT code, func_a, func_b, roles_a, roles_b, severity, enforced_by
                    FROM sec_sod_pairs
                   WHERE active=1 AND severity='block' AND scope='role'
                     AND roles_a<>'' AND roles_b<>'' AND roles_b<>'*'
                   ORDER BY code");
while ($x = $pq->fetch_assoc()) { $pairs[] = $x; }

$screensOf = function ($csv) use ($db) {
    $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
    if (!$ids) { return array(); }
    $in = implode(',', $ids);
    $r = $db->query("SELECT DISTINCT m.code FROM role_permissions rp
                       JOIN modules m ON m.id = rp.module_id
                      WHERE rp.role_id IN ($in) AND rp.can_view = 1");
    $o = array();
    while ($z = $r->fetch_row()) { $o[$z[0]] = 1; }
    return $o;
};

/* بنودُ كلِّ قالبٍ نافذٍ مرّةً واحدة. */
$profItems = array(); $profMeta = array();
$r = $db->query("SELECT p.profile_id, p.profile_code, p.title_ar, i.item_ref
                   FROM gov_role_profiles p
                   LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id
                        AND i.item_kind = 'screen' AND i.allow = 1
                  WHERE p.state = 'active'");
while ($x = $r->fetch_assoc()) {
    $pid = (int) $x['profile_id'];
    if (!isset($profItems[$pid])) { $profItems[$pid] = array(); $profMeta[$pid] = $x['profile_code'] . ' — ' . $x['title_ar']; }
    if ($x['item_ref'] !== null) { $profItems[$pid][$x['item_ref']] = 1; }
}
$holders = array();
$r = $db->query("SELECT g.profile_id, COUNT(DISTINCT g.user_id) n
                   FROM gov_authority_grants g JOIN users u ON u.id = g.user_id AND $LIVE
                  WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                  GROUP BY g.profile_id");
while ($x = $r->fetch_assoc()) { $holders[(int) $x['profile_id']] = (int) $x['n']; }

$hits = array(); $checked = 0;
foreach ($pairs as $p) {
    $A = $screensOf($p['roles_a']);
    $B = $screensOf($p['roles_b']);
    $exA = array_diff_key($A, $B);
    $exB = array_diff_key($B, $A);
    if (!$exA || !$exB) { continue; }   /* بلا حصريٍّ لطرفٍ لا يُقاس التعارض */
    $checked++;
    foreach ($profItems as $pid => $items) {
        $ia = count(array_intersect_key($items, $exA));
        $ib = count(array_intersect_key($items, $exB));
        if ($ia > 0 && $ib > 0) {
            $hits[] = array('pair' => $p['code'], 'pid' => $pid, 'a' => $ia, 'b' => $ib,
                            'users' => isset($holders[$pid]) ? $holders[$pid] : 0,
                            'fa' => $p['func_a'], 'fb' => $p['func_b'],
                            'ra' => $p['roles_a'], 'rb' => $p['roles_b']);
        }
    }
}
printf("   تركيباتٌ قابلةٌ للقياس: %d من %d · قوالبُ نافذةٌ فُحصت: %d\n\n", $checked, count($pairs), count($profItems));
if (!$hits) {
    echo "   ✔ لا قالبَ نافذًا يجمع حصريَّ طرفَي تركيبةٍ حرجة\n";
} else {
    printf("   %-8s %-30s %6s %6s %8s\n", 'تركيبة', 'القالب', 'حصريA', 'حصريB', 'حاملون');
    foreach ($hits as $h) {
        printf("   %-8s %-30s %6d %6d %8d\n", $h['pair'], mb_substr($profMeta[$h['pid']], 0, 28), $h['a'], $h['b'], $h['users']);
    }
    printf("\n   ACTIVE_PROFILE_WITH_P0_SOD = %d  (المستهدَف صفر)\n", count($hits));
    $withUsers = 0;
    foreach ($hits as $h) { if ($h['users'] > 0) { $withUsers++; } }
    printf("   منها ما يحمله فاعلٌ حيٌّ = %d\n", $withUsers);
}

/* ═══ ②-ب · تقييدُ الكشف — `gov_sod_conflict` كان فارغًا فلا يُعرف أوقع كشفٌ ═══
   ◆ **الكاشفُ الذي لا يكتب لا يُحاسَب**: سجلٌّ فارغٌ يُقرأ «لا تعارضَ» وهو في
     الحقيقةِ «لا كاشفَ». فالكتابةُ هنا تحوّل الرقمَ من مخرجِ أداةٍ إلى واقعةٍ
     مقيَّدةٍ يراها المراجعُ والامتثال.
   ◆ **وعاطلةٌ بوسمِ المصدر**: صفوفُ هذا الكاشفِ وحدَها تُمحى قبلَ الكتابةِ —
     فلا يُدهَس كشفٌ من مصدرٍ آخرَ ولا تتضاعف الصفوفُ بإعادةِ التشغيل. */
if ($WRITE) {
    echo "\n══ ②-ب تقييدُ الكشفِ في gov_sod_conflict ══════════════════════════════\n";
    $SRC = 'PERM-01 §4 · perm01_p0_containment';

    /* ◆ **الحبّةُ تتبع دلالةَ السجلِّ لا شكلَ مخرجي**: مفتاحُه الفريدُ
         (شركة × تركيبة × دورٍ مكشوفٍ × مستخدم) — فالحبّةُ **تركيبةٌ × دور**،
         لا تركيبةٌ × قالب. فتُجمَع قوالبُ الدورِ الواحدِ في صفٍّ واحدٍ يسمّيها،
         وإلا اصطدمت الصفوفُ بالمفتاحِ وضاع أكثرُها صامتًا.
       ◆ ودورُ الكشفِ هو **دورُ حاملِ القالب** لا دورٌ في التركيبةِ نظريًّا —
         فالمكشوفُ من يحمل، لا من قد يحمل. */
    $roleOfProfile = array();
    $rr = $db->query("SELECT g.profile_id, u.role
                        FROM gov_authority_grants g
                        JOIN users u ON u.id = g.user_id AND $LIVE
                       WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
                       GROUP BY g.profile_id, u.role");
    while ($x = $rr->fetch_assoc()) { $roleOfProfile[(int) $x['profile_id']][] = (int) $x['role']; }

    $agg = array();
    foreach ($hits as $h) {
        $roles = isset($roleOfProfile[$h['pid']]) ? $roleOfProfile[$h['pid']] : array(0);
        foreach ($roles as $rid) {
            $k = $h['pair'] . '|' . $rid;
            if (!isset($agg[$k])) {
                $agg[$k] = array('pair' => $h['pair'], 'role' => $rid, 'fa' => $h['fa'],
                                 'fb' => $h['fb'], 'profiles' => array(), 'users' => 0);
            }
            $agg[$k]['profiles'][] = $profMeta[$h['pid']];
            $agg[$k]['users'] += $h['users'];
        }
    }

    $del = $db->prepare("DELETE FROM gov_sod_conflict WHERE src_ref = ?");
    $del->bind_param('s', $SRC); $del->execute();
    $removed = $db->affected_rows; $del->close();

    /* ⛔ `state` محكومٌ بـ`chk_gsc_state` ∈ (defined·detected·mitigated·accepted·closed)
         — و«مكشوفٌ لم يُعالَج» هو `detected` لا `open`. والقيدُ ردَّ الكتابةَ أوّلَ مرّة. */
    $ins = $db->prepare(
        "INSERT INTO gov_sod_conflict
           (company_id, conflict_code, title_ar, side_a, side_b, process_key,
            detected_role_id, detected_user_id, detected_at, mitigation_ar,
            state, src_ref, severity)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, 'detected', ?, 'block')
         ON DUPLICATE KEY UPDATE title_ar = VALUES(title_ar), process_key = VALUES(process_key),
            mitigation_ar = VALUES(mitigation_ar), detected_at = NOW(), src_ref = VALUES(src_ref)");
    $wrote = 0;
    foreach ($agg as $a) {
        $codes = array();
        foreach ($a['profiles'] as $pm) { $codes[] = trim(explode('—', $pm)[0]); }
        $codes = array_values(array_unique($codes));
        $title = 'قوالب نافذة تجمع حصري طرفي ' . $a['pair'] . ': ' . implode(' · ', $codes);
        $title = mb_substr($title, 0, 200);
        $pk = mb_substr(implode(',', $codes), 0, 64);
        $mit = $a['users'] > 0
             ? ('يحمله ' . $a['users'] . ' فاعلا حيا — والحد الصلب على الفعل هو الضابط القائم')
             : 'بلا حامل حي — يعالج قبل اسناده';
        $mit = mb_substr($mit, 0, 400);
        $code = $a['pair']; $fa = mb_substr($a['fa'], 0, 120); $fb = mb_substr($a['fb'], 0, 120);
        $rid = (int) $a['role'];
        $ins->bind_param('isssssiss', $CO, $code, $title, $fa, $fb, $pk, $rid, $mit, $SRC);
        if ($ins->execute()) { $wrote++; }
        else { echo '   ✘ ' . $code . '/' . $rid . ': ' . $ins->error . "\n"; }
    }
    $ins->close();
    printf("   حُذف من هذا المصدر: %d · كُتب: %d صفًّا (تركيبة × دور) · والسجلُّ الآن: %d\n",
        $removed, $wrote, $one('SELECT COUNT(*) FROM gov_sod_conflict'));
    echo "   ◆ الحالة `detected` — والحسمُ قرارُ مالكٍ يُكتب في `treatment_decision`.\n";
}

/* ═══ ③ الشاهدُ الصناعيّ — أيرى الكاشفُ تعارضًا نعلمه؟ ═══════════════════ */
echo "\n══ ③ الشاهدُ الصناعيّ — قبلَ أن يُقرأ صفرٌ براءةً ══════════════════════\n";
$A = $screensOf('34'); $B = $screensOf('35');
$exA = array_diff_key($A, $B); $exB = array_diff_key($B, $A);
printf("   SOD-06 (34 × 35): حصريُّ الأوّل=%d · حصريُّ الثاني=%d %s\n",
    count($exA), count($exB),
    (count($exA) === 0 || count($exB) === 0)
        ? '⛔ الدوران متطابقا الشاشات — فالتعارضُ لا يُقاس بالشاشةِ أصلًا، ويُقاس بالفعل'
        : '✔ قابلٌ للقياس');
$synthetic = 0;
foreach ($profItems as $pid => $items) {
    if (count(array_intersect_key($items, $A)) > 0 && count(array_intersect_key($items, $B)) > 0) { $synthetic++; }
}
printf("   قوالبُ نافذةٌ تحمل شاشاتِ الدورَين معًا (لا الحصريَّ): %d %s\n",
    $synthetic, $synthetic > 0 ? '✔ الكاشفُ يرى' : '⛔ صفرٌ على حالةٍ معلومة');

/* ═══ ④ الفاعلُ الواحدُ وطرفا التركيبة ═════════════════════════════════ */
echo "\n══ ④ تركيبةٌ حرجةٌ يحملها فاعلٌ واحد ══════════════════════════════════\n";
$both = 0;
foreach ($pairs as $p) {
    $ia = implode(',', array_filter(array_map('intval', explode(',', $p['roles_a']))));
    $ib = implode(',', array_filter(array_map('intval', explode(',', $p['roles_b']))));
    if ($ia === '' || $ib === '') { continue; }
    $n = $one("SELECT COUNT(*) FROM users u WHERE $LIVE AND u.role IN ($ia) AND u.role IN ($ib)");
    if ($n > 0) { printf("   ⛔ %s — %d فاعلًا\n", $p['code'], $n); $both += $n; }
}
printf("   SOD_CRITICAL_PAIR_HELD_BY_ONE_PRINCIPAL = %d  (المستهدَف صفر)\n", $both);
echo "   ◆ والدورُ عمودٌ واحدٌ في `users` فلا يجتمع دوران في فاعلٍ بنيويًّا —\n";
echo "     فالمقياسُ يُغلَق بالبنيةِ لا بالانضباط، والخطرُ يبقى في القالبِ والفعل.\n";

/* ═══ ⑤ الفارقُ على أعلامِ الكتابة — لا العرضِ وحدَه ═══════════════════ */
echo "\n══ ⑤ الفارقُ على أعلامِ الكتابة (PERM-01 §3-③) ════════════════════════\n";
$COV = "EXISTS(SELECT 1 FROM gov_authority_grants g
                 JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state='active'
                WHERE g.user_id = u.id AND g.revoked_at IS NULL
                  AND (g.valid_to IS NULL OR g.valid_to > NOW()))";
printf("   %-10s %10s %10s %10s %10s\n", 'العَلَم', 'يتّفقان', '②>①', '①>②', 'أزواج');
foreach (array('can_view' => 'عرض', 'can_add' => 'إضافة', 'can_edit' => 'تعديل', 'can_delete' => 'حذف') as $f => $lbl) {
    $tf = ($f === 'can_view') ? 'i.allow' : 'i.' . $f;
    $sql = "SELECT SUM(tv=rv) same, SUM(tv=1 AND rv=0) tpl, SUM(tv=0 AND rv=1) rol, COUNT(*) n
              FROM (SELECT
                      (SELECT COALESCE(MAX(CASE WHEN i.allow=1 THEN $tf ELSE 0 END),0)
                         FROM gov_authority_grants g
                         JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
                         JOIN gov_profile_items i ON i.profile_id=p.profile_id
                              AND i.item_kind='screen' AND i.item_ref=c.code
                        WHERE g.user_id=u.id AND g.revoked_at IS NULL
                          AND (g.valid_to IS NULL OR g.valid_to>NOW())) tv,
                      (SELECT COALESCE(MAX(rp.$f),0) FROM role_permissions rp
                         JOIN modules m ON m.id=rp.module_id
                        WHERE rp.role_id=u.role AND m.code=c.code) rv
                    FROM users u CROSS JOIN (SELECT DISTINCT code FROM modules) c
                   WHERE $LIVE AND $COV) x";
    $r = $db->query($sql);
    if (!$r) { printf("   %-10s تعذّر: %s\n", $lbl, $db->error); continue; }
    $z = $r->fetch_assoc();
    printf("   %-10s %10s %10s %10s %10s\n", $lbl,
        number_format($z['same']), number_format($z['tpl']), number_format($z['rol']), number_format($z['n']));
}
echo "   ◆ «②>①» توسعةٌ · و«①>②» تضييق — ولا يُجمعان.\n";

/* ═══ ⑥ تغطيةُ حارسِ الفعلِ لأفعالِ الدورة ═════════════════════════════ */
echo "\n══ ⑥ تغطيةُ حارسِ الفعلِ لأفعالِ الدورةِ البنكيّة ══════════════════════\n";
require_once $ROOT . '/includes/action_guard.php';
$reg = function_exists('ems_action_guard_registry') ? ems_action_guard_registry() : array();
$mode = function_exists('ems_env') ? (string) ems_env('EMS_ACTION_GUARD', 'monitor') : 'monitor';
printf("   معالجاتٌ مسجَّلةٌ في السجل: %d · الوضع: %s\n", count($reg), $mode);
$fin = 0; $finKeys = array();
foreach ($reg as $k => $v) {
    if (preg_match('~(finance|fin_|treasury|payment|bank|journal|voucher)~i', (string) $k)) { $fin++; $finKeys[] = $k; }
}
printf("   منها تخصُّ المال/الخزينة/البنك: %d %s\n", $fin, $fin === 0 ? '⛔ لا فعلَ ماليٌّ محروسٌ بالسجل' : '');
foreach (array_slice($finKeys, 0, 6) as $k) { echo "      · $k\n"; }
$declared = $col("SELECT DISTINCT action_codes FROM gov_authority_limits WHERE action_codes<>'' AND doc_code LIKE 'FIN-%'");
$codes = array();
foreach ($declared as $d) { foreach (explode(',', $d) as $c) { $c = trim($c); if ($c !== '') { $codes[$c] = 1; } } }
printf("   أفعالٌ مُعلَنةٌ في المصدرِ الحاكمِ لأوراقِ FIN: %d · %s\n", count($codes), implode(' · ', array_slice(array_keys($codes), 0, 8)));
printf("   منها موجودةٌ في سجلِّ حارسِ الفعل: %d\n",
    count(array_intersect(array_keys($codes), array_keys($reg))));
echo "   ⛔ فالضبطُ المالِيُّ اليومَ في `ApprovalGate` و`AssignmentGate` لا في حارسِ الفعل —\n";
echo "     و`P0_SOD_FORBIDDEN_ACTION_COMBINATION_EXECUTABLE` **غيرُ مقيسٍ** حتى تُربط الأفعالُ برموزِها.\n";

echo "\n────────────────────────────────────────────────────────────────────────\n";
echo "الوحدةُ: الكودُ المتمايز · والمقامُ: مستخدمون أحياءُ مغطَّون بقالبٍ نافذ.\n";
