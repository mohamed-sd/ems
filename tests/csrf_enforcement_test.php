<?php
/**
 * tests/csrf_enforcement_test.php — شاهدُ إنفاذِ رمزِ الحماية
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0022 · INJ-0044 · INJ-0050 · INJ-0051 · INJ-0057
 *                  INJ-0066 · INJ-0106 · INJ-0160 · INJ-0173 · INJ-0194
 *                  INJ-0226 · INJ-0290 · INJ-0469 · INJ-0480 · INJ-0530
 *
 * **العيب**: عشراتُ اختباراتِ القبولِ تطلب «POST بلا رمزٍ صالحٍ يُرفض». والإنفاذُ
 * كان على **خمسةِ مجلداتٍ** فقط (`CSRF_ENFORCE_PATHS`)، فالشرطُ غيرُ محقَّقٍ
 * **بنيويًّا** خارجَها — وطلبٌ بلا رمزٍ يمرُّ بـ200.
 *
 * ── ولماذا لم يُوسَّع قبلَ اليوم ─────────────────────────────────────────────
 * فخٌّ مسجَّل: حين وُسِّع سابقًا رُدَّت شاشاتٌ بنماذجَ عاديةٍ بـ403 **لكلِّ
 * مستخدم**. فقيس أوّلًا **المُخرَجُ الحيُّ** لا المصدر: `ems_inject_csrf_fields()`
 * تحقن الرمزَ في كلِّ `<form method=post>` عند التصيير — وقيست **4,498 نموذجًا
 * مُصيَّرًا في 200 شاشةٍ بصفرِ نموذجٍ بلا رمز**. فالتوسيعُ لا يكسر نموذجًا.
 *
 * ── والطرفانِ يُقاسان ────────────────────────────────────────────────────────
 *   ① طلبٌ **بلا رمزٍ** على مسارٍ مُنفَذٍ ⇒ يُردُّ (403) ولا يكتب صفًّا.
 *   ② وطلبٌ **برمزٍ صالحٍ** على المسارِ نفسِه ⇒ يمرُّ ويكتب.
 * فإنفاذٌ يرفض الجميعَ ليس إنفاذًا بل عطلًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ إنفاذُ رمزِ الحماية — الطرفانِ معًا');

$TAG = 'CSRFPROBE';
$sweep = function () use ($conn, $TAG) {
    $n = 0; $ids = array();
    $r = $conn->query("SELECT id FROM job_titles WHERE name LIKE '%" . $TAG . "%'");
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $id) {
        $conn->query("DELETE FROM activity_logs WHERE screen_name = 'job_titles' AND record_id = " . $id);
        if ($conn->query('DELETE FROM job_titles WHERE id = ' . $id)) { $n += $conn->affected_rows; }
    }
    return $n;
};
$pre = $sweep();
if ($pre) { $say("   (كُنس {$pre} من جولةٍ سابقة)"); }

/* ── ① القائمةُ وُسِّعت ─────────────────────────────────────────────────────── */
require_once $ROOT . '/includes/env.php';
$paths = array_values(array_filter(array_map('trim', explode(',', (string) ems_env('CSRF_ENFORCE_PATHS', '')))));
$ok(count($paths) >= 20, 'قائمةُ الإنفاذِ تغطّي ' . count($paths) . ' مجلدًا (كانت 5)',
    'العدد: ' . count($paths));
$ok(in_array('/Employees/', $paths, true) && in_array('/Financing/', $paths, true)
    && in_array('/Procurement/', $paths, true) && in_array('/Governance/', $paths, true),
    'ومنها المجلداتُ التي تطلبها اختباراتُ القبول');
$ok(!in_array('/admin/', $paths, true),
    'ولوحةُ الإدارةِ العليا **خارجَها** — لها حمايتُها الخاصةُ ولا تمرُّ بالحاقن');

/* ── ② حسابٌ يملك الإضافةَ على شاشةِ القياس ──────────────────────────────── */
$u = null; $roleUsed = 0;
$q = $conn->query("SELECT rp.role_id FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                    WHERE m.code = 'Employees/job_titles.php' AND rp.can_add = 1 ORDER BY rp.role_id");
while ($q && ($rr = $q->fetch_row())) {
    $st = $conn->prepare("SELECT id, username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $rid = (string) $rr[0];
    $st->bind_param('si', $rid, $CO);
    $st->execute();
    $cand = $st->get_result()->fetch_assoc();
    $st->close();
    if ($cand) { $u = $cand; $roleUsed = (int) $rr[0]; break; }
}
$ok($u !== null, 'وُجد حسابٌ مخوَّلٌ (' . ($u ? $u['username'] . ' · دور ' . $roleUsed : '—') . ')');
if ($u === null) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

$jar = sys_get_temp_dir() . '/csrfenf_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$http($BASE . '/login.php', http_build_query(array(
    'username' => $u['username'], 'password' => '12345678',
    'csrf_token' => isset($t[1]) ? $t[1] : '')));
$scr = $http($BASE . '/Employees/job_titles.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $scr['body'], $ct);
$tok = isset($ct[1]) ? $ct[1] : '';
$ok($tok !== '', 'وصُيِّرت الشاشةُ **برمزٍ في نموذجِها** — الحاقنُ المركزيُّ يفي');

$countRows = function () use ($conn, $TAG) {
    $r = $conn->query("SELECT COUNT(*) FROM job_titles WHERE name LIKE '%" . $TAG . "%'");
    return $r ? (int) $r->fetch_row()[0] : -1;
};

/* ── ③ الطرفُ الأول: **بلا رمزٍ** ⇒ يُردُّ ولا يكتب ────────────────────────── */
$before = $countRows();
$noTok = $http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'name' => 'بلا رمزٍ ' . $TAG, 'description' => 'يجب ألا يُكتب',
    'status' => 1, 'sort_order' => 1)));
$afterNo = $countRows();
$ok($noTok['code'] === 403, '**طلبٌ بلا رمزٍ يُردُّ 403**', 'الرمزُ ' . $noTok['code']);
$ok($afterNo === $before, '**ولا يكتب صفًّا** (' . $before . ' ⇒ ' . $afterNo . ')');

/* ── ④ الطرفُ الثاني: **برمزٍ صالحٍ** ⇒ يمرُّ ويكتب ─────────────────────────── */
$withTok = $http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'csrf_token' => $tok, 'name' => 'برمزٍ ' . $TAG, 'description' => 'يجب أن يُكتب',
    'status' => 1, 'sort_order' => 2)));
$afterYes = $countRows();
$ok($withTok['code'] < 400, 'وطلبٌ برمزٍ صالحٍ **يمرُّ** (' . $withTok['code'] . ')');
$ok($afterYes === $before + 1, '**ويكتب صفَّه** (' . $before . ' ⇒ ' . $afterYes . ')',
    'الفرقُ ' . ($afterYes - $before));

/* ── ⑤ ورمزٌ **مزوَّرٌ** يُردُّ كالغائب ───────────────────────────────────────── */
$badTok = $http($BASE . '/Employees/job_titles.php', http_build_query(array(
    'csrf_token' => str_repeat('a', 64), 'name' => 'رمزٌ مزوَّرٌ ' . $TAG,
    'description' => 'يجب ألا يُكتب', 'status' => 1, 'sort_order' => 3)));
$afterBad = $countRows();
$ok($badTok['code'] === 403 && $afterBad === $afterYes,
    'ورمزٌ مزوَّرٌ يُردُّ 403 ولا يكتب', 'الرمزُ ' . $badTok['code'] . ' · الصفوفُ ' . $afterBad);

@unlink($jar);
$post = $sweep();
$say("   كُنس ختامًا: {$post} صفًّا");
$ok($countRows() === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
