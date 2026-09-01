<?php
/**
 * tests/gov_exec_action_exercise_proof.php — برهانُ **ممارسةِ** فعلِ التسجيلِ حيًّا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: أمرُ `GOV_EXEC` §3 ينصُّ على `RENDER/EXERCISE` لا على التصييرِ
 *   وحدَه. وبرهانُ التصييرِ يثبت أنَّ الرؤوسَ تظهر — **ولا يثبت أنَّ الزرَّ يعمل**.
 *   ودرسٌ مقيسٌ في هذه الجولةِ بعينِها: ستّةٌ وسبعون فعلًا مُعلَنًا في الشاشاتِ
 *   المبنيّةِ **ولا واحدٌ منها مسجَّلٌ في `nav09_action_map`**، وعُدّةُ `u13`
 *   تردُّ غيرَ المسجَّلِ بـ`fail-closed` — فالزرُّ يُعرض ويفشل عند الضغط،
 *   والتصييرُ أخضرُ والفعلُ ميّت. **فالتصييرُ ليس ممارسة.**
 *
 * ◆ **ما يُثبته** على سطحٍ واحدٍ من كلِّ مواصفةٍ يعلن فعلًا:
 *   ① دخولٌ بدورِ المساحةِ · ② `POST` بفعلِ التسجيلِ برمزِ حمايةٍ صحيح ·
 *   ③ الصفحةُ تردُّ **✓ لا ✗** · ④ **صفٌّ جديدٌ في الجدولِ فعلًا** (العدُّ قبلَ وبعد)
 *   · ⑤ ثمَّ يُكنس الصفُّ المزروعُ بمعرِّفِه — فلا يترك البرهانُ أثرًا.
 *
 * ⛔ **والسالبُ يُقاس**: `POST` بلا رمزِ حمايةٍ يُردّ — فالنجاحُ ليس بابًا مفتوحًا.
 *
 * التشغيل: php tests/gov_exec_action_exercise_proof.php [--spec=tools/specs/x.php]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$BASE = getenv('EMS_BASE_URL') ?: 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }

$specs = array();
foreach ($argv as $a) { if (strpos($a, '--spec=') === 0) { $specs[] = substr($a, 7); } }
if (!$specs) { $specs = glob($ROOT . '/tools/specs/*.php'); }

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ DB: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

function ax_req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 90));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($code, substr($raw, 0, (int) $hs), substr($raw, (int) $hs));
}

foreach ($specs as $sp) {
    if (!is_file($sp)) { continue; }
    $S = require $sp;
    if (!isset($S['screens'])) { continue; }
    /* أوّلُ سطحٍ يعلن فعلًا — واحدٌ يكفي لإثباتِ أنَّ السلسلةَ حيّة */
    $pick = null;
    foreach ($S['screens'] as $sc) { if (!empty($sc['create'])) { $pick = $sc; break; } }
    if ($pick === null) { continue; }
    $rel = $S['dir'] . '/' . $pick['file'];
    fwrite(STDOUT, "\n── {$S['dept']} · {$rel}\n");

    /* الرمزُ مسجَّلٌ في القاموسِ أوّلًا — وإلا فالفعلُ ميّتٌ قبلَ أن يُضغَط */
    $ac = !empty($pick['action_code']) ? $pick['action_code'] : ('gov.' . $pick['code'] . '.register');
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code = '"
                    . $conn->real_escape_string($ac) . "' LIMIT 1");
    if (!$q || !$q->num_rows) { bad("رمزُ الفعلِ «{$ac}» غيرُ مسجَّلٍ في القاموس — الزرُّ يُعرض ولا يعمل"); continue; }
    ok("رمزُ الفعلِ مسجَّل: {$ac}");

    /* ⛔ **والمُمارِسُ من يملك الكتابةَ بالمسارَين** — لا أوّلُ مستخدمٍ بالدور:
         الصلاحيةُ طبقتان (منحةُ الدورِ في `role_permissions` وقالبُ المستخدمِ
         في `gov_profile_items`) والأضيقُ يحكم. ومستخدمٌ قالبُه `can_edit=0`
         **لا يُصيَّر له زرُّ الفعلِ أصلًا** — فقياسُه «الفعلُ لا يعمل» قياسٌ
         على غيرِ مَحلِّه: الشاشةُ سليمةٌ والمنحةُ ضيّقةٌ بقرارِها.
         ⇒ يُختار من يملكها، وإن لم يوجد **يُسمّى الحاجزُ ولا يُرسَّب السطح**. */
    $rel0e = $conn->real_escape_string($rel);
    $q = $conn->query("SELECT u.username FROM users u
        WHERE u.role = '" . (int) $S['role_id'] . "' AND (u.is_deleted IS NULL OR u.is_deleted = 0)
          AND EXISTS (SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                       WHERE rp.role_id = u.role AND rp.can_edit = 1 AND m.code = '{$rel0e}')
          AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g
                            JOIN gov_profile_items gi ON gi.profile_id = g.profile_id
                           WHERE g.user_id = u.id AND g.revoked_at IS NULL
                             AND gi.item_kind = 'screen' AND gi.item_ref = '{$rel0e}'
                             AND gi.can_edit = 0)
        ORDER BY u.id LIMIT 1");
    $ur = $q ? $q->fetch_row() : null;
    if (!$ur) {
        fwrite(STDOUT, "  ○ لا مستخدمَ بصلاحيةِ كتابةٍ فعليّةٍ على هذا السطح — "
             . "**حاجزُ منحةٍ لا عطبُ شاشة** (منحةُ الدورِ قائمةٌ وقالبُ المستخدمِ can_edit=0) · "
             . "يُرفع لمالكِ الصلاحيات\n");
        continue;
    }
    $jar = sys_get_temp_dir() . '/ax_' . getmypid() . '.txt';
    @unlink($jar);
    list($c, $h, $b) = ax_req($BASE . '/login.php', $jar);
    $tok = '';
    if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m)) { $tok = $m[1]; }
    ax_req($BASE . '/login.php', $jar, array('username' => $ur[0], 'password' => '12345678', 'csrf_token' => $tok));

    $tbl = $pick['table'];
    $before = (int) $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];

    list($c, $h, $b) = ax_req($BASE . '/' . $rel, $jar);
    $tok = '';
    if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m)) { $tok = $m[1]; }

    /* ⛔ السالبُ أوّلًا: بلا رمزِ حمايةٍ يُردّ */
    $mark = 'EXERCISE-' . getmypid();
    $post = array('u13_action' => 'register', 'csrf_token' => 'bad-token');
    foreach ($pick['create'] as $k) { $post[$k] = $mark; }
    list($c, $h, $b2) = ax_req($BASE . '/' . $rel, $jar, $post);
    $midCount = (int) $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
    if ($midCount === $before) { ok('السالب: فعلٌ بلا رمزِ حمايةٍ لم يكتب صفًّا'); }
    else { bad('السالب: كُتب صفٌّ برغمِ رمزِ حمايةٍ فاسد'); }

    /* ثمَّ الموجب */
    $post['csrf_token'] = $tok;
    list($c, $h, $b3) = ax_req($BASE . '/' . $rel, $jar, $post);
    $after = (int) $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
    $said = (mb_strpos($b3, '✓') !== false);
    $why = '';
    if (preg_match('~alert alert-(?:danger|success)">([^<]{0,140})~u', $b3, $mm)) { $why = trim($mm[1]); }
    if ($after === $before + 1 && $said) {
        ok(sprintf('الموجب: الفعلُ كتب صفًّا فعلًا (%d ⇐ %d) · «%s»', $before, $after, mb_substr($why, 0, 70)));
    } else {
        bad(sprintf('الموجب: %d ⇐ %d · ردُّ الشاشة: «%s»', $before, $after, mb_substr($why, 0, 110)));
    }

    /* الكنس: ما زُرع يُنزَع بمعرِّفِه — ولا يترك البرهانُ أثرًا */
    $cols = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($tbl) . "'");
    $has = array();
    while ($cols && ($x = $cols->fetch_row())) { $has[$x[0]] = 1; }
    $k0 = $pick['create'][0];
    if (isset($has[$k0])) {
        $conn->query("DELETE FROM `{$tbl}` WHERE `{$k0}` = '" . $conn->real_escape_string($mark) . "'");
        $left = (int) $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
        if ($left === $before) { ok('الكنس: صفرُ أثرٍ باقٍ من البرهان'); }
        else { bad("الكنس: بقي {$left} بدل {$before}"); }
    }
    @unlink($jar);
}
fwrite(STDOUT, sprintf("\n── الحصيلة: ✔ %d · ✘ %d\n", $PASS, $FAIL));
exit($FAIL > 0 ? 1 : 0);
