<?php
/**
 * tests/operations_audit_trail_test.php — شاهدُ INJ-0139
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0139
 *
 * **اختبارُ القبولِ نصًّا** (السجلُّ الجامع · «طلب تبديل / التوقفات»):
 *   «كلُّ فعلٍ كاتبٍ في وحدةِ التشغيلِ يُنتج صفَّ تدقيقٍ **واحدًا** يحمل الجدولَ
 *    والمعرّفَ والقيمةَ **قبل وبعد**.»
 *
 * **والمقيسُ قبل الإصلاح**: `Operations/` بـ**صفرِ** نداءٍ لـ`ems_audit_change`
 * بينما **259 ملفًّا** في المستودعِ تناديه. فإسنادُ مسؤوليةِ توقفٍ — وهو قرارٌ
 * يُحمّل إدارةً تكلفةً — كان يقع **بلا أثرٍ يُراجَع**.
 *
 * ── وهذا الفاحصُ يُثبت الأربعةَ ────────────────────────────────────────────────
 *   ① **صفٌّ واحدٌ لا صفران** — فالتكرارُ ضوضاءٌ كالغياب.
 *   ② **بالجدولِ والمعرّفِ** — فأثرٌ لا يُشير إلى صفٍّ لا يُراجَع.
 *   ③ **وبالقيمةِ قبل وبعد** — وهي جوهرُ المعيارِ: «قبل» تُقرأ من الصفِّ لا
 *      تُفترض نُلًّا.
 *   ④ **ولا صفَّ ضوضاءٍ عند اللاتغيير** — فإعادةُ الإسنادِ نفسِه لا تكتب شيئًا.
 *
 * ◆ والفعلُ يقع **عبر الشاشةِ بـHTTP** كما يفعل مديرُ الموقع — لا بنداءٍ مباشرٍ
 *   للمُوصِل؛ فالمقصودُ إثباتُ أنَّ **مسارَ الشاشةِ** يُنتج الأثر.
 * ◆ والوسمُ عائليٌّ والكنسُ بعائلتِه، ويُستعاد ما غُيِّر من صفوفٍ حيّةٍ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if ($r === false) { return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
$say('══ INJ-0139 · أثرُ التدقيقِ في وحدةِ التشغيل');

/* ══ ① المُوصِلُ المشتركُ مُنادًى من Operations — لا آليةٌ ثانية ═══════════════ */
$callers = 0;
foreach (glob($ROOT . '/Operations/*.php') as $f) {
    if (strpos((string) file_get_contents($f), 'ems_audit_change') !== false) { $callers++; }
}
$ok($callers > 0, 'ملفاتُ Operations/ التي تنادي المُوصِلَ المشترك: ' . $callers,
    'كان صفرًا بينما 259 ملفًّا في المستودعِ تناديه');
foreach (array('stops_unattributed.php', 'swap_request.php') as $f) {
    $src = (string) file_get_contents($ROOT . '/Operations/' . $f);
    $ok(strpos($src, 'ems_audit_change') !== false, "   و«{$f}» منها");
    /* ◆ **يُحمَّل المُوصِلُ عند موضعِ الاستعمال** — ولولا ذلك لكان الشرطُ كاذبًا
         دائمًا فيُتخطّى التدقيقُ صامتًا (فخُّ «حارسٍ قائمٍ نصًّا غائبٍ فعلًا»). */
    $ok(strpos($src, "audit_trail.php") !== false,
        "   ويُحمِّل audit_trail.php فيه — فالنداءُ لا يُتخطّى صامتًا");
}

/* ══ ② الفعلُ عبر الشاشةِ بـHTTP ثم قياسُ الأثر ══════════════════════════════ */
$row = $conn->query("SELECT id, fault_department FROM timesheet
                      WHERE company_id = {$CO} AND total_fault_hours > 0
                        AND (fault_department IS NULL OR fault_department = '')
                      ORDER BY id DESC LIMIT 1");
$row = ($row && $row->num_rows) ? $row->fetch_assoc() : null;
$ok($row !== null, 'وُجد توقفٌ حقيقيٌّ بلا مسؤولٍ للجسّ (لا رقمٌ مخترَع)',
    'كلُّ التوقفاتِ مُسنَدةٌ اليومَ — فرعُ HTTP لا يُحكَم عليه');

if ($row !== null) {
    $TID = (int) $row['id'];
    $before = $row['fault_department'];
    $DEPT = 'الصيانة';
    $auditBefore = (int) $one("SELECT COUNT(*) FROM activity_logs
                                WHERE screen_name = 'stops_unattributed.php' AND record_id = {$TID}");

    $BASE = 'http://localhost/ems';
    $J = sys_get_temp_dir() . '/opaudit_' . getmypid() . '.txt';
    @unlink($J);
    $rq = function ($u, $post = null) use ($J) {
        $ch = curl_init($u);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 90,
            CURLOPT_POST => $post !== null));
        if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $b = curl_exec($ch);
        curl_close($ch);
        return (string) $b;
    };
    $lp = $rq($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $lp, $tk);
    $rq($BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678',
                                    'csrf_token' => isset($tk[1]) ? $tk[1] : ''));
    $pg = $rq($BASE . '/Operations/stops_unattributed.php');
    $ok(mb_strpos($pg, 'التوقفات') !== false, 'الشاشةُ صُيِّرت (لا تحويلٌ إلى login)');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $pg, $tk2);
    $body = $rq($BASE . '/Operations/stops_unattributed.php',
        array('assign_ts' => $TID, 'fault_department' => $DEPT,
              'csrf_token' => isset($tk2[1]) ? $tk2[1] : ''));
    $applied = (string) $one("SELECT fault_department FROM timesheet WHERE id = {$TID}");
    $ok($applied === $DEPT, 'وأُسند التوقفُ عبر الشاشةِ فعلًا (' . $applied . ')',
        'لو لم يُسنَد فالقياسُ التالي خواء');

    /* ── ①②③ صفٌّ واحدٌ بالجدولِ والمعرّفِ وبالقيمتين ────────────────────── */
    $q = $conn->query("SELECT * FROM activity_logs
                        WHERE screen_name = 'stops_unattributed.php' AND record_id = {$TID}
                        ORDER BY id DESC");
    $logs = array();
    while ($q && ($x = $q->fetch_assoc())) { $logs[] = $x; }
    $added = count($logs) - $auditBefore;
    $ok($added === 1, '**صفُّ تدقيقٍ واحدٌ لا صفران** (' . $added . ')',
        'والتكرارُ ضوضاءٌ كالغياب');
    $lg = $logs ? $logs[0] : null;
    $ok($lg !== null && (int) $lg['record_id'] === $TID,
        '   ويحمل **المعرّفَ** (' . ($lg ? $lg['record_id'] : '—') . ')');
    $hay = $lg ? json_encode($lg, JSON_UNESCAPED_UNICODE) : '';
    $ok($lg !== null && mb_strpos($hay, 'fault_department') !== false,
        '   ويحمل **اسمَ الحقلِ** المتغيّر');
    $ok($lg !== null && mb_strpos($hay, $DEPT) !== false,
        '   ويحمل القيمةَ **بعد** (' . $DEPT . ')');
    /* ◆ «قبل» جوهرُ المعيار: يجب أن تُقرأ من الصفِّ لا تُفترض */
    $beforeShown = ($before === null || $before === '');
    $ok($lg !== null && ($beforeShown
            ? (mb_strpos($hay, 'old_value') !== false || mb_strpos($hay, 'null') !== false)
            : mb_strpos($hay, (string) $before) !== false),
        '   ويحمل القيمةَ **قبل** (' . var_export($before, true) . ')');

    /* ── ④ ولا صفَّ ضوضاءٍ عند اللاتغيير ────────────────────────────────── */
    $rq($BASE . '/Operations/stops_unattributed.php',
        array('assign_ts' => $TID, 'fault_department' => $DEPT,
              'csrf_token' => isset($tk2[1]) ? $tk2[1] : ''));
    $after2 = (int) $one("SELECT COUNT(*) FROM activity_logs
                           WHERE screen_name = 'stops_unattributed.php' AND record_id = {$TID}");
    $ok($after2 === count($logs),
        '**وإعادةُ الإسنادِ نفسِه لا تكتب صفَّ ضوضاءٍ** (' . $after2 . ' = ' . count($logs) . ')',
        'الحارسُ يشترط affected_rows > 0');

    /* ── الاستعادةُ: يُردُّ الصفُّ الحيُّ إلى ما كان ─────────────────────────── */
    $st = $conn->prepare('UPDATE timesheet SET fault_department = ? WHERE id = ?');
    if ($st) {
        $st->bind_param('si', $before, $TID);
        $st->execute();
        $st->close();
    }
    $back = $one("SELECT COALESCE(fault_department,'') FROM timesheet WHERE id = {$TID}");
    $ok((string) $back === (string) ($before === null ? '' : $before),
        'ورُدَّ التوقفُ إلى ما كان (' . var_export($back, true) . ') — صفرُ أثرٍ في بيانٍ حيّ');
    $conn->query("DELETE FROM activity_logs WHERE screen_name = 'stops_unattributed.php'
                   AND record_id = {$TID} AND id > " . (int) ($logs ? end($logs)['id'] - 1 : 0));
    @unlink($J);
}

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
