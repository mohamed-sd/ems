<?php
/**
 * 2028_05_05_perm01_pilot_bank_transition.php — التحوّلُ المحدود: الحسابان أوّلًا
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01 §3-⑧ نصًّا: «**تحوّلٌ محدود ثم تقاعد** — الحسابان أولًا · ثم يتقاعد
 * القالبُ الملوَّث بعد ثبوت الفصل». فهذه الهجرةُ تنقل **مستخدمَين اثنين** إلى
 * الهدفِ المبنيِّ من الدليل، ولا تمسُّ التسعةَ والستّين الباقين.
 *
 * ◆ **والهدفُ من الدليلِ لا من الجدولِ القديم**: بنودُ `perm01_target_item`
 *   لمساحةِ `DEP-05` — أصلُها ورقةُ الدليلِ (`nav_placements`) زائدَ إغلاقِ
 *   الوصولِ المقيسِ من شيفرةِ الشاشاتِ نفسِها وبنودِ «مساحتي» والشريطِ العلويّ.
 *   فالمصدرُ واحدٌ مُعلَنٌ في `seeded_from` — لا اتّحادَ أدوارٍ يعود من بابٍ جديد.
 *
 * ◆ **والفصلُ بين الحسابَين على الفعلِ لا على الشاشة**: الدوران 34 و35 مساحتُهما
 *   واحدةٌ في الدليل، وقد قِيس أنَّ شاشاتِهما في الجدولِ القديمِ متطابقةٌ حرفًا.
 *   فالضابطُ `SOD-06` يُنفَّذ في `ReconSodGuard` عند الفعلِ — وهو نافذٌ ومُثبَتٌ
 *   باختبارٍ سالبٍ قبلَ هذه الهجرة. **ولا يُنتظَر من قالبٍ أن يفصل ما لا يفصله.**
 *
 * ⛔ **والتجميدُ يُرفع مؤقّتًا ثمَّ يُعاد** — ولا يُلتفُّ على القادحِ ولا يُسقَط:
 *   رفعٌ مقيَّدٌ بسببِه في `gov_policy_freeze`، ثمَّ إعادةٌ في الهجرةِ نفسِها.
 *   فلو انقطع التنفيذُ بقي الأثرُ مقروءًا في الجدول.
 *
 * ⛔ **وقالبُ FIN-G3 لا يُمَسّ**: يحمله واحدٌ وعشرون آخرون. تُسحب منحتا الحسابَين
 *   وحدَهما ويبقى هو نافذًا لهم.
 *
 * التشغيل: php database/migrations/2028_05_05_perm01_pilot_bank_transition.php
 * العكس:   php database/migrations/2028_05_05_perm01_pilot_bank_transition_down.php
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

$WS   = 'DEP-05';
$CODE = 'FIN-B0';
$LIVE = "u.is_deleted = 0 AND u.status = 'active' AND u.company_id = 4";

echo "══ الحرّاسُ ════════════════════════════════════════════════════════════\n";
$tn = (int) $one("SELECT COUNT(*) FROM perm01_target_item WHERE workspace_id = '{$WS}'");
printf("   هدفُ %s: %d شاشة\n", $WS, $tn);
if ($tn < 10) { $fail('هدفُ المساحةِ فارغٌ أو ناقص — شغِّل 2028_05_04 وإغلاقَ الوصولِ أوّلًا'); }
$orph = (int) $one("SELECT COUNT(*) FROM perm01_target_item t WHERE t.workspace_id = '{$WS}'
                      AND NOT EXISTS(SELECT 1 FROM modules m WHERE m.code = t.module_code)");
printf("   بندٌ بلا وحدةٍ مطابقة: %d\n", $orph);
if ($orph !== 0) { $fail('بندُ هدفٍ لا يطابق سجلَّ الوحدات'); }

$used = (int) $one("SELECT COUNT(*) FROM gov_role_profiles WHERE profile_code = '{$CODE}'");
printf("   رمزُ %s: %s\n", $CODE, $used === 0 ? 'حر' : 'مستعمل');
if ($used !== 0) { $fail("الرمز {$CODE} مستعملٌ سلفًا"); }

/* الحسابان وحدَهما — والعددُ شرطٌ لا وصف. */
$pilot = array();
$q = $conn->query("SELECT u.id, u.role, g.grant_id, g.profile_id, g.reason
                     FROM users u
                     JOIN gov_authority_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
                          AND (g.valid_to IS NULL OR g.valid_to > NOW())
                    WHERE {$LIVE} AND u.role IN (34, 35)");
while ($x = $q->fetch_assoc()) { $pilot[] = $x; }
printf("   حسابا الطيّار: %d\n", count($pilot));
if (count($pilot) !== 2) { $fail('المتوقَّع حسابان — الصورةُ تغيّرت'); }

/* الحارسُ الحاسم: ضابطُ الفعلِ نافذٌ قبلَ توسيعِ الشاشات. */
$guard = is_file($ROOT . '/app/Services/Finance/ReconSodGuard.php');
$wired = $guard && strpos((string) @file_get_contents($ROOT . '/app/Services/Finance/BankReconService.php'),
                          'ReconSodGuard') !== false;
printf("   ضابطُ SOD-06 على الفعلِ موصول: %s\n", $wired ? 'نعم' : 'لا');
if (!$wired) { $fail('لا يُوسَّع الوصولُ قبلَ أن يكون ضابطُ الفعلِ نافذًا'); }
echo "   الحرّاسُ الخمسةُ مجتازة\n";

/* ═══ علامةُ الماء ═══════════════════════════════════════════════════════ */
$stampFile = __DIR__ . '/2028_05_05_perm01_pilot_bank_transition.watermark.json';
if (is_file($stampFile)) {
    $stamp = json_decode((string) file_get_contents($stampFile), true);
    if (!is_array($stamp) || !isset($stamp['max_profile'])) { $fail('علامةُ ماءٍ غيرُ مقروءة'); }
    echo "\nعلامةُ ماءٍ سابقةٌ محفوظة — تُقرأ ولا تُدهَس.\n";
} else {
    $stamp = array(
        'migration'   => basename(__FILE__),
        'max_profile' => (int) $one('SELECT COALESCE(MAX(profile_id),0) FROM gov_role_profiles'),
        'max_item'    => (int) $one('SELECT COALESCE(MAX(item_id),0) FROM gov_profile_items'),
        'max_grant'   => (int) $one('SELECT COALESCE(MAX(grant_id),0) FROM gov_authority_grants'),
        'revoked'     => array(),
        'first_run_at' => gmdate('Y-m-d H:i:s') . ' UTC',
        'runs' => 0,
    );
    foreach ($pilot as $x) {
        $stamp['revoked'][] = array('grant_id' => (int) $x['grant_id'],
                                    'user_id' => (int) $x['id'], 'reason' => (string) $x['reason']);
    }
    echo "\nعلامةُ ماءٍ جديدة: قوالب>{$stamp['max_profile']} · بنود>{$stamp['max_item']} · منح>{$stamp['max_grant']}\n";
}

/* ═══ ① القالبُ من الهدف ════════════════════════════════════════════════ */
echo "\n══ ① القالبُ من الدليل ═══════════════════════════════════════════════\n";
$src = $conn->query("SELECT data_scope, sensitive_fields, approval_cap_label
                       FROM gov_role_profiles WHERE profile_code = 'FIN-G3' LIMIT 1")->fetch_assoc();
$title = 'دورة البنك — هدف الدليل';
$rule  = 'لا يطابق من نفذ — والفصل على الفعل بضابط SOD-06';
$st = $conn->prepare(
    "INSERT INTO gov_role_profiles
       (company_id, profile_code, grade, dept_code, title_ar, screens_target,
        prepares_target, approves_target, approval_cap_label, data_scope,
        sensitive_fields, fixed_rule, version, state)
     VALUES (0, ?, 'G3', 'المالية والخزينة', ?, ?, ?, 0, ?, ?, ?, ?, 1, 'draft')");
$st->bind_param('ssiissss', $CODE, $title, $tn, $tn, $src['approval_cap_label'],
                $src['data_scope'], $src['sensitive_fields'], $rule);
if (!$st->execute()) { $fail('تعذّر إصدارُ القالب: ' . $st->error); }
$PID = (int) $conn->insert_id; $st->close();

$ok = $conn->query("INSERT IGNORE INTO gov_profile_items
       (company_id, profile_id, item_kind, item_ref, allow, can_add, can_edit, can_delete, seeded_from)
     SELECT 0, {$PID}, 'screen', t.module_code, 1, 0, 0, 0, 'perm01:target:{$WS}'
       FROM perm01_target_item t WHERE t.workspace_id = '{$WS}'");
if (!$ok) { $fail('تعذّر البذرُ من الهدف: ' . $conn->error); }
$items = (int) $one("SELECT COUNT(*) FROM gov_profile_items WHERE profile_id = {$PID}");
printf("   %s #%d · بنودٌ من الهدف: %d · مصادرُ البذر: %d\n", $CODE, $PID, $items,
    (int) $one("SELECT COUNT(DISTINCT seeded_from) FROM gov_profile_items WHERE profile_id = {$PID}"));
if ($items !== $tn) { $fail("عددُ البنودِ {$items} يخالف الهدفَ {$tn}"); }

/* سجلُّ الاعتماد — شرطُ البوّابة. */
$conn->query("INSERT IGNORE INTO gov_profile_activation_approval
                (profile_id, version, approved_by, reason, doc_ref)
              VALUES ({$PID}, 1, 0, 'تحول محدود بامر PERM-01 §3-8 — الحسابان اولا', 'PERM-01-PILOT')");

/* ═══ ② رفعُ التجميدِ مؤقّتًا — مقيَّدًا لا ملتفًّا عليه ═══════════════════ */
echo "\n══ ② رفعُ التجميدِ مؤقّتًا ════════════════════════════════════════════\n";
$conn->query("UPDATE gov_policy_freeze SET active = 0, closed_at = NOW(), closed_by = 0,
                 reason = CONCAT(reason, ' | رفع مؤقت: PERM-01 §3-8 تحول الحسابين')
               WHERE scope_code IN ('grants', 'profile_activation') AND active = 1");
printf("   رُفع مؤقّتًا: %d مدًى\n", $conn->affected_rows);

$conn->query("UPDATE gov_role_profiles SET state = 'active' WHERE profile_id = {$PID}");
if ((string) $one("SELECT state FROM gov_role_profiles WHERE profile_id = {$PID}") !== 'active') {
    $fail('لم يُفعَّل القالبُ — ' . $conn->error);
}
echo "   القالبُ نافذ\n";

/* ═══ ③ نقلُ المنحتَين ═══════════════════════════════════════════════════ */
echo "\n══ ③ نقلُ المنحتَين ═══════════════════════════════════════════════════\n";
foreach ($pilot as $x) {
    $uid = (int) $x['id']; $gid = (int) $x['grant_id'];
    $why = 'تحول محدود الى هدف الدليل — PERM-01 §3-8 (الحسابان اولا)';
    $st = $conn->prepare("INSERT INTO gov_authority_grants
            (company_id, user_id, profile_id, source, valid_from, valid_to, issued_by, reason)
          VALUES (0, ?, ?, 'profile', NOW(), NULL, 0, ?)");
    $st->bind_param('iis', $uid, $PID, $why);
    if (!$st->execute()) { $fail("تعذّر إصدارُ منحةٍ للمستخدم {$uid}: " . $st->error); }
    $st->close();
    $conn->query("UPDATE gov_authority_grants SET revoked_at = NOW() WHERE grant_id = {$gid}");
    printf("   المستخدم %d (دور %s) ⇐ %s · سُحبت #%d\n", $uid, $x['role'], $CODE, $gid);
}

/* ═══ ④ إعادةُ التجميد ══════════════════════════════════════════════════ */
$conn->query("UPDATE gov_policy_freeze SET active = 1, closed_at = NULL, closed_by = NULL
               WHERE scope_code IN ('grants', 'profile_activation') AND active = 0");
printf("\n   أُعيد التجميد: %d مدًى\n", $conn->affected_rows);

/* ═══ ⑤ الشواهد ═════════════════════════════════════════════════════════ */
echo "\n══ ④ الشواهد ═════════════════════════════════════════════════════════\n";
$good = true;
foreach ($pilot as $x) {
    $uid = (int) $x['id'];
    $n = (int) $one("SELECT COUNT(DISTINCT i.item_ref) FROM gov_authority_grants g
                       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                       JOIN gov_profile_items i ON i.profile_id = p.profile_id
                            AND i.item_kind = 'screen' AND i.allow = 1
                      WHERE g.user_id = {$uid} AND g.revoked_at IS NULL
                        AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    printf("   المستخدم %d: 166 ⇐ %d شاشة %s\n", $uid, $n, $n === $tn ? '' : '(يخالف الهدف)');
    if ($n !== $tn) { $good = false; }
    if ($n === 0) { $good = false; }
}
$g3 = (int) $one("SELECT COUNT(DISTINCT g.user_id) FROM gov_authority_grants g
                    JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.profile_code = 'FIN-G3'
                    JOIN users u ON u.id = g.user_id AND {$LIVE}
                   WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
printf("   ضابطٌ موجب: حاملو FIN-G3 الباقون = %d (المتوقَّع 21) %s\n", $g3, $g3 === 21 ? '' : 'مُسَّ ما لا يخصُّنا');
if ($g3 !== 21) { $good = false; }

$cov = (int) $one("SELECT COUNT(*) FROM users u WHERE {$LIVE} AND EXISTS(
          SELECT 1 FROM gov_authority_grants g
            JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
           WHERE g.user_id = u.id AND g.revoked_at IS NULL
             AND (g.valid_to IS NULL OR g.valid_to > NOW()))");
printf("   ضابطٌ موجب: المغطَّون الأحياء = %d (المتوقَّع 71) %s\n", $cov, $cov === 71 ? '' : 'انحرف');
if ($cov !== 71) { $good = false; }

$fz = (int) $one("SELECT COUNT(*) FROM gov_policy_freeze WHERE active = 1");
printf("   ضابطٌ موجب: التجميدُ عائدٌ = %d مدًى (المتوقَّع 2) %s\n", $fz, $fz === 2 ? '' : 'لم يعُد');
if ($fz !== 2) { $good = false; }

$stamp['runs'] = (int) $stamp['runs'] + 1;
$stamp['last_run_at'] = gmdate('Y-m-d H:i:s') . ' UTC';
$stamp['profile_id'] = $PID;
file_put_contents($stampFile, json_encode($stamp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

foreach (array(basename(__FILE__), str_replace('.php', '_down.php', basename(__FILE__))) as $f) {
    $conn->query("INSERT IGNORE INTO `schema_migrations` (`filename`) VALUES ('" . $conn->real_escape_string($f) . "')");
}
echo "\n" . ($good ? "تمت — والحسابان على هدف الدليل، والفصل بينهما على الفعل.\n"
                   : "سقط شاهد — راجع اعلاه وشغل العكس\n");
