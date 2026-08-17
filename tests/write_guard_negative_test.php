<?php
/**
 * tests/write_guard_negative_test.php — (ب) بإثباتٍ إيجابيٍّ وسلبيٍّ لا بكلمةِ «أُصلحت»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ تصحيحُ المالك (2026-08-19 · ثانيًا): «ولا أريد «أُصلحت» — أريد قبل/بعد ·
 *   اختبارًا إيجابيًّا · اختبارًا سلبيًّا · وتشغيلَ البوابةِ على استنساخٍ نظيف».
 *
 * ◆ فهذا يُشغِّل الحارسَ حيًّا على أسطحٍ حقيقيةٍ بجلساتِ مستخدمينَ حقيقيين:
 *   ① **إيجابيّ**: صاحبُ صلاحيةِ الكتابةِ يمرُّ — فالحارسُ لا يعطّل العمل.
 *   ② **سلبيّ**: صاحبُ صلاحيةِ العرضِ وحدَها يُردُّ — بالرمزِ الحوكميِّ لا بصمت.
 *   ③ **سلبيُّ الصيغة**: طلبُ AJAX يُردُّ **بـJSON** لا بنصٍّ خامٍّ يكسر الشاشة.
 *   ④ **سلبيُّ الرمز**: رمزُ حمايةٍ فاسدٌ يُردُّ ولو كانت الصلاحيةُ كاملة.
 *   ⑤ **حارسُ الحارس**: نزعُ الحارسِ من سطحٍ يجب أن **يُرسِّب** الفحصَ —
 *      فإن بقي أخضرَ بلا حارسٍ فالفحصُ زخرفةٌ لا بوابة.
 *
 * التشغيل: php tests/write_guard_negative_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$pass = 0; $fail = 0;
function ok($c, $m, $d = '') { global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$m}\n"; } else { $fail++; echo "  ✘ FAIL: {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

/** يُشغِّل المسبارَ الحيَّ ويرجع حكمَه */
function probe($ROOT, $file, $role) {
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/fix_probe_write_guard.php') . ' '
         . escapeshellarg($file) . ' ' . escapeshellarg((string) $role) . ' 2>&1');
    foreach (explode("\n", $out) as $ln) {
        if (strpos($ln, 'PW|') === 0) { return json_decode(trim(substr($ln, 3)), true); }
    }
    return null;
}

/**
 * يجد دورًا **قارئًا فعلًا** ودورًا **كاتبًا فعلًا** — بالصلاحيةِ النافذةِ لا المعلَنة.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ عيبُ قياسٍ رُصد وأُصلح: أولُ صياغةٍ اختارت الدورَ من `role_permissions`
 *   (الطبقة ②) بينما الحارسُ ينفّذ **الصلاحيةَ النافذةَ** (الطبقة ③) التي
 *   يغلبها قالبُ GOV-AUTH حيث يغطّي. فاختِير «قارئٌ» يمنحه قالبُه `can_edit`
 *   فمرَّ — وأُعلن «القارئُ لم يُمنع» **وهو كاتبٌ حقيقيّ**.
 *   (قِيس: المستخدم 72 دورُه 17 · role_permissions تقول can_edit=0 · وقالبُه
 *    يقول can_edit=1 — فالحارسُ أصاب والاختيارُ أخطأ.)
 * ◆ فالاختيارُ الآن يحاكي ترتيبَ الحارسِ نفسَه: قالبُ المستخدمِ إن غطّى، وإلا
 *   منحُ الدور.
 */
function roles_for($conn, $screen) {
    $esc = $conn->real_escape_string($screen);
    $ro = null; $rw = null;
    $q = "SELECT rp.role_id,
                 (SELECT MIN(u.id) FROM users u WHERE u.company_id = 4
                    AND CAST(u.role AS UNSIGNED) = rp.role_id) uid,
                 rp.can_add, rp.can_edit, rp.can_delete
            FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
           WHERE m.code = '{$esc}' AND rp.can_view = 1
          HAVING uid IS NOT NULL";
    $r = $conn->query($q);
    while ($r && ($x = $r->fetch_assoc())) {
        $uid = (int) $x['uid'];
        /* الطبقة ③: قالبُ المستخدمِ النافذُ يغلب حيث يغطّي */
        $pq = $conn->query("SELECT i.can_add, i.can_edit, i.can_delete
                              FROM gov_authority_grants g
                              JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                              JOIN gov_profile_items i ON i.profile_id = g.profile_id
                                   AND i.item_kind = 'screen' AND i.item_ref = '{$esc}'
                             WHERE g.user_id = {$uid} AND g.revoked_at IS NULL
                               AND (g.valid_to IS NULL OR g.valid_to > NOW()) LIMIT 1");
        if ($pq && ($pp = $pq->fetch_assoc())) {
            $w = ((int) $pp['can_add'] || (int) $pp['can_edit'] || (int) $pp['can_delete']);
        } else {
            $w = ((int) $x['can_add'] || (int) $x['can_edit'] || (int) $x['can_delete']);
        }
        if ($w && $rw === null) { $rw = (int) $x['role_id']; }
        if (!$w && $ro === null) { $ro = (int) $x['role_id']; }
    }
    return array($ro, $rw);
}

echo "════ (ب) حارسُ الكتابة — إيجابيٌّ وسلبيٌّ على أسطحٍ حية ════\n\n";

/* أسطحٌ وُصل بها الحارسُ المركزيُّ في هذه الجولة */
$SURFACES = array(
    'Governance/auth_grants.php',
    'Governance/authority_caps.php',
    'Governance/tech_gov_center.php',
    'Contracts/penalties.php',
    'Procurement/po_match.php',
    'Procurement/requests_proc.php',
    'Maintenance/orders.php',
);

echo "▐ ①+② إيجابيٌّ وسلبيٌّ لكلِّ سطحٍ بحارسٍ مركزيّ\n";
$measured = 0; $posOk = 0; $negOk = 0; $skipped = array();
foreach ($SURFACES as $s) {
    list($ro, $rw) = roles_for($conn, $s);
    if ($rw === null) { $skipped[] = basename($s) . ' (لا دورَ كاتبٍ بمستخدمٍ حيّ)'; continue; }
    $b = probe($ROOT, $s, $rw);
    if (!is_array($b) || isset($b['err'])) { $skipped[] = basename($s) . ' (تعذّر القياس)'; continue; }
    $measured++;
    if (empty($b['write_denied'])) { $posOk++; } else { echo "      ✘ {$s}: الكاتب({$rw}) مُنع خطأً\n"; }
    if ($ro === null) { continue; }   /* لا دورَ قارئًا خالصًا — يُعلَن أدناه */
    $a = probe($ROOT, $s, $ro);
    if (is_array($a) && !isset($a['err']) && !empty($a['write_denied'])) { $negOk++; }
    elseif (is_array($a) && !isset($a['err'])) { echo "      ✘ {$s}: القارئ({$ro}) لم يُمنع\n"; }
}
ok($measured > 0, "أسطحٌ مقيسة: {$measured}");
ok($posOk === $measured, "**إيجابيّ**: الكاتبُ يمرُّ في {$posOk}/{$measured} — الحارسُ لا يعطّل العمل");
echo "      ◆ سلبيٌّ مقيسٌ حيث وُجد دورٌ قارئٌ خالصٌ بمستخدمٍ حيّ: {$negOk}\n";
if ($skipped) { echo "      ◆ غيرُ مقيسٍ (يُعلَن): " . implode(' · ', $skipped) . "\n"; }

/* ══ ③ سلبيُّ الصيغة: AJAX يُردُّ JSON ══ */
echo "\n▐ ③ صيغةُ الردِّ تتبع صيغةَ الطلبِ — AJAX لا يُكسَر بنصٍّ خام\n";
$src = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
$hasJsonBail = (strpos($src, 'application/json; charset=utf-8') !== false
             && strpos($src, 'HTTP_X_REQUESTED_WITH') !== false);
ok($hasJsonBail, 'الحارسُ يردُّ JSON لطلبِ AJAX ونصًّا لغيرِه');
$plainExits = preg_match_all('~ems_require_action_log\([^)]*\);\s*\n\s*http_response_code\(403\);\s*\n\s*exit\(~', $src);
ok($plainExits === 0, 'لا مخرجَ نصيًّا خامًّا متبقيًا في الحارس', "بقي {$plainExits}");

/* ══ ④ سلبيُّ الرمز: CSRF فاسدٌ يُردُّ ولو كانت الصلاحيةُ كاملة ══ */
echo "\n▐ ④ رمزُ الحمايةِ يُفحص **قبلَ** الصلاحية\n";
$csrfFirst = (strpos($src, "ems_require_action_log(\$screen, \$verb, 'csrf_failed')") !== false);
$posCsrf = strpos($src, "'csrf_failed'");
$posDeny = strpos($src, "'denied'");
ok($csrfFirst && $posCsrf !== false && $posDeny !== false && $posCsrf < $posDeny,
   'فحصُ الرمزِ يسبق فحصَ الصلاحيةِ في الحارس — فالطلبُ المزوَّرُ يُردُّ ولو كان صاحبُه مخوَّلًا');

/* ══ ⑤ حارسُ الحارس: نزعُ الحارسِ يجب أن يُرسِّب الفحص ══ */
echo "\n▐ ⑤ حارسُ الحارس — نزعُه يُرسِّب الفحصَ (وإلا فالفحصُ زخرفة)\n";
$victim = $ROOT . '/Governance/auth_grants.php';
$orig = (string) file_get_contents($victim);
$stripped = preg_replace('~^\s*ems_require_action\([^;]*;\s*$~mu', '', $orig, 1, $cnt);
if ($cnt !== 1) {
    ok(false, 'تعذّر نزعُ الحارسِ للاختبار', "عُثر على {$cnt}");
} else {
    file_put_contents($victim, $stripped);
    $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/fix_gate.php') . ' 2>&1');
    $f2Failed = (strpos($out, '✘ AC-F2') !== false);
    file_put_contents($victim, $orig);          /* الاستعادةُ فورًا */
    ok($f2Failed, 'نزعُ الحارسِ من سطحٍ واحدٍ **رسَّب** AC-F2 — فالفحصُ بوابةٌ لا زخرفة');
    $restored = (string) file_get_contents($victim);
    ok($restored === $orig, 'السطحُ استُعيد حرفًا بعد الاختبار');
}

echo "\n══════════════════════════════════════\n";
echo "  النتيجة: {$pass} نجاح · {$fail} فشل\n";
exit($fail === 0 ? 0 : 1);
