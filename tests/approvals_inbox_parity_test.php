<?php
/**
 * tests/approvals_inbox_parity_test.php — INJ-0587: رقمُ البلاطةِ = صفوفُ الصندوق
 * ═══════════════════════════════════════════════════════════════════════════
 * معيارُ القبولِ في السجلِّ الجامع نصًّا: «الرقمُ على بلاطةِ ﴿موافقاتي﴾ في
 * `main/my_workspace.php` = عددُ الصفوفِ في `Portal/approvals_inbox.php`
 * بالضبط، **لكلِّ دور**».
 *
 * ◆ **وهذا الفاحصُ لا يقنع بمساواةِ رقمين.** مساواةٌ على بياناتٍ لا تُشغّل
 *   الفرقَ خواءٌ لا شهادة — وقد وقع: أوّلُ قياسٍ أعطى «74 مستخدمًا · صفرُ
 *   انحرافٍ» بينما المسارانِ مختلفان فعلًا، لأنَّ صفرَ رابطٍ كان موجَّهًا بدورٍ.
 *   فيُثبِتُ هنا كلُّ انحرافٍ **بتشغيلِه**: يُزرع الصفُّ الذي يُفرِّق التعريفَين،
 *   ويُشترط أن يتحرّك الرقمان معًا — ثم يُرفع الزرعُ.
 *
 * الوسمُ عائليٌّ (`AIP` + رقمُ العملية) والكنسُ **بعائلةِ الوسمِ** لا بالوسمِ
 * وحدَه، لأنَّ جولةً ماتت تترك ثغرةً حيةً تُعمي الجولةَ التالية.
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: INJ-0587
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/approvals_inbox_scope.php';

$conn = $GLOBALS['conn'];
$CO = 4;
$FAMILY = 'AIP';
$MARK = $FAMILY . getmypid();
$PASS = 0; $FAIL = 0;

$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* ── كنسُ عائلةِ الوسمِ قبل البدء (ثغرةُ جولةٍ ماتت) ───────────────────────── */
$swept = 0;
$conn->query("DELETE FROM approval_links WHERE company_id = {$CO} AND source_ref LIKE '{$FAMILY}%'");
$swept += $conn->affected_rows;
$say("══ INJ-0587 · تكافؤُ البلاطةِ والصندوق  (كُنس {$swept} من عائلةِ {$FAMILY})");

/* عمودُ approval_links الحقيقيُّ — يُثبَت لا يُحدَس */
$cols = array();
$r = $conn->query("SHOW COLUMNS FROM approval_links");
while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x; }
$need = array('company_id', 'status', 'approver_user_id', 'approver_role', 'source_kind', 'source_ref');
foreach ($need as $cn) {
    $ok(isset($cols[$cn]), "عمودٌ قائمٌ: approval_links.{$cn}");
}
if ($FAIL > 0) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }

/* ══ ① المصدرُ واحدٌ — لا نصَّ شرطٍ ثانيًا في القارئين ════════════════════════ */
$tile = file_get_contents($ROOT . '/main/my_workspace.php');
$inbox = file_get_contents($ROOT . '/Portal/approvals_inbox.php');
$ok(strpos($tile, 'ems_approvals_inbox_counts') !== false,
    'البلاطةُ تقرأ المصدرَ الواحد');
$ok(strpos($inbox, 'ems_approvals_inbox_where') !== false,
    'الصندوقُ يقرأ المصدرَ الواحد');
/* والدليلُ الأقوى: لا يُكتب الشرطُ المميِّزُ بيدٍ ثانيةٍ في أيٍّ منهما */
$ok(strpos($tile, "status = 'pending' AND approver_user_id") === false,
    'البلاطةُ لا تكتب شرطَ approval_links بيدها');
$ok(!preg_match("~\\\$stageRoles\s*=\s*array~", $inbox),
    'الصندوقُ لا يحمل خريطةَ مراحلَ ثانيةً');
$ok(!preg_match("~LIMIT\s+60~", $inbox),
    'سقفُ العرضِ مُعلَنٌ مرةً (ems_approvals_inbox_cap) لا ثلاثًا برقمٍ صلب');
$ok(strpos($tile, "state='converted' AND ue.converted_at IS NULL") === false
    && strpos($tile, "ue.state='converted'") === false,
    'البلاطةُ لا تكرّر نصَّ ems_uc_prechain_sql()');

/* ══ ② التكافؤُ لكلِّ دورٍ على بياناتِ اليوم ══════════════════════════════════
     يُقاس بالمُعامِلَين اللذين تستعملهما الشاشتان فعلًا: العدُّ من
     `ems_approvals_inbox_counts` والسطورُ من ذاتِ شروطِ `..._where`. */
$users = array();
$r = $conn->query("SELECT id, role FROM users
                    WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0
                    ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $users[] = $x; }

/** يعدُّ السطورَ التي يُدرجها الصندوقُ فعلًا بذاتِ شروطِه (بلا سقفٍ) */
$rowsOf = function ($uid, $role, $isSuper) use ($conn, $CO) {
    $w = ems_approvals_inbox_where($conn, $CO, $uid, $role, $isSuper);
    $n = 0;
    $q = array(
        "SELECT COUNT(*) c FROM requests rq JOIN request_types rt ON rt.code = rq.request_type_code WHERE " . $w['requests'],
        "SELECT COUNT(*) c FROM approval_links al WHERE " . $w['approval_links'],
    );
    if ($w['unit_entries'] !== null) {
        $q[] = "SELECT COUNT(*) c FROM unit_entries ue WHERE " . $w['unit_entries'];
    }
    foreach ($q as $s) { $rr = $conn->query($s); if ($rr) { $n += (int) reset($rr->fetch_assoc()); } }
    return $n;
};

$bad = array();
foreach ($users as $u) {
    $uid = (int) $u['id']; $role = (string) $u['role']; $isS = ($role === '-1');
    $c = ems_approvals_inbox_counts($conn, $CO, $uid, $role, $isS);
    $rows = $rowsOf($uid, $role, $isS);
    if ((int) $c['total'] !== $rows) { $bad[] = "u{$uid}/r{$role}: بلاطةٌ {$c['total']} ≠ صندوقٌ {$rows}"; }
}
$ok(count($bad) === 0, 'تكافؤٌ على ' . count($users) . ' مستخدمًا لكلِّ دور',
    implode(' · ', array_slice($bad, 0, 3)));

/* ══ ③ **إثباتُ عدمِ الخواء** — يُشغَّل كلُّ انحرافٍ ويُشترط تحرُّكُ الرقمين ═════ */

/* ①-حيًّا: رابطٌ موجَّهٌ **بدورٍ** (approver_user_id IS NULL) — كان يُعرَض ولا يُعَدّ */
$victim = null;
foreach ($users as $u) { if ((string) $u['role'] !== '-1') { $victim = $u; break; } }
$ok($victim !== null, 'وُجد مستخدمٌ غيرُ سوبر للجسّ');
if ($victim !== null) {
    $vid = (int) $victim['id']; $vrole = (string) $victim['role'];
    $before = (int) ems_approvals_inbox_counts($conn, $CO, $vid, $vrole, false)['total'];
    $rBefore = $rowsOf($vid, $vrole, false);

    $st = $conn->prepare("INSERT INTO approval_links
        (company_id, status, approver_user_id, approver_role, source_kind, source_ref, step_no)
        VALUES (?, 'pending', NULL, ?, 'test', ?, 1)");
    $srcRef = $MARK . '-ROLE';
    $kind = 'test';
    $st->bind_param('iss', $CO, $vrole, $srcRef);
    $inserted = $st->execute();
    $st->close();
    $ok($inserted === true, 'زُرع رابطٌ موجَّهٌ بدورٍ (يُشغّل الانحرافَ ①)',
        $inserted ? '' : $conn->error);

    if ($inserted) {
        $after = (int) ems_approvals_inbox_counts($conn, $CO, $vid, $vrole, false)['total'];
        $rAfter = $rowsOf($vid, $vrole, false);
        /* ◆ الشرطُ الحاسم: **ارتفع الرقمان معًا**. لو بقيت البلاطةُ حيث كانت
             فالتعريفُ ما زال اثنين — وهذا بعينِه ما يرسب الفاحصُ عليه. */
        $ok($after === $before + 1,
            'رقمُ البلاطةِ ارتفع بالرابطِ الموجَّهِ بدورٍ (' . $before . ' ⇒ ' . $after . ')');
        $ok($rAfter === $rBefore + 1,
            'سطورُ الصندوقِ ارتفعت كذلك (' . $rBefore . ' ⇒ ' . $rAfter . ')');
        $ok($after === $rAfter, 'وبقيا متساويين بعد الزرع');

        $conn->query("DELETE FROM approval_links WHERE company_id = {$CO} AND source_ref = '"
                     . $conn->real_escape_string($srcRef) . "'");
        $removed = $conn->affected_rows;
        $ok($removed === 1, 'رُفع الزرعُ (' . $removed . ' صفًّا)');
        $back = (int) ems_approvals_inbox_counts($conn, $CO, $vid, $vrole, false)['total'];
        $ok($back === $before, 'وعاد الرقمُ إلى ما كان (' . $back . ')');
    }
}

/* ④-حيًّا: السوبرُ يرى كلَّ المراحلِ — فمراحلُه أوسعُ من مراحلِ أيِّ دور */
$sSuper = ems_approvals_my_stages('-1', true);
$sRole1 = ems_approvals_my_stages('1', false);
$ok(count($sSuper) === count(ems_approvals_stage_roles()),
    'السوبرُ يعتمد كلَّ المراحلِ (' . count($sSuper) . ')');
$ok(count($sRole1) < count($sSuper),
    'ودورٌ عاديٌّ أضيقُ منه (' . count($sRole1) . ' < ' . count($sSuper) . ')',
    'لو تساويا لكان فرعُ السوبرِ خاويًا');
/* والانحرافُ ④ كان يُقاس حيًّا: عدُّ السوبرِ على كلِّ المراحلِ ≠ عدُّه على مرحلةٍ */
$qAll = $conn->query("SELECT COUNT(*) c FROM unit_entries ue WHERE ue.company_id = {$CO}
                       AND ue.state IN ('" . implode("','", $sSuper) . "')
                       AND NOT " . ems_uc_prechain_sql('ue'));
$nAll = $qAll ? (int) reset($qAll->fetch_assoc()) : -1;
$qOne = $conn->query("SELECT COUNT(*) c FROM unit_entries ue WHERE ue.company_id = {$CO}
                       AND ue.state IN ('" . implode("','", $sRole1) . "')
                       AND NOT " . ems_uc_prechain_sql('ue'));
$nOne = $qOne ? (int) reset($qOne->fetch_assoc()) : -1;
$ok($nAll > $nOne,
    'والفرقُ مُشغَّلٌ بالبيانات: السوبرُ ' . $nAll . ' مقابل دورِ 1 ' . $nOne,
    'لو تساويا لصار فرعُ ④ خاويًا فلا يُصدَّق');

/* ⑤: السقفُ مُعلَنٌ، والعدُّ الصادقُ يفوقه — فالإفصاحُ لازمٌ لا زخرفة */
$cap = ems_approvals_inbox_cap();
$ok($cap > 0, 'سقفُ العرضِ مُعلَنٌ (' . $cap . ')');
$super = null;
foreach ($users as $u) { if ((string) $u['role'] === '-1') { $super = $u; break; } }
$probeRole = $super ? '-1' : '1';
$probeUid = $super ? (int) $super['id'] : (int) $users[0]['id'];
$pc = ems_approvals_inbox_counts($conn, $CO, $probeUid, $probeRole, $probeRole === '-1');
$ok((int) $pc['total'] >= (int) $pc['shown'],
    'الصادقُ ≥ المعروض (' . $pc['total'] . ' ≥ ' . $pc['shown'] . ')');
$ok(strpos($inbox, "يُعرَض") !== false && strpos($inbox, '$__cnt[\'total\']') !== false,
    'الصندوقُ يُفصح عن المجموعِ الصادقِ وعن المقصوصِ منه');
/* ②: المتعذِّرُ عرضُه يُعلَن لا يُدفَن */
$ok(isset($pc['untyped']), 'الطلباتُ بنوعٍ غيرِ مسجَّلٍ محسوبةٌ ومُعلَنة (' . $pc['untyped'] . ')');
$ok(strpos($inbox, 'untyped') !== false, 'والصندوقُ يعرضها إن وُجدت');

/* ══ ⑥ **الشاشتان نفسُهما** — لا الدالتان ═══════════════════════════════════
     ◆ هذا الفرعُ وُلد من خطإِ قياسٍ وقع لي: قِستُ الدالتين فأعطتا تكافؤًا،
       ثم قرأتُ الصفحةَ فرأيتُ «3» مقابل «1501» — وكان «3» شارةَ رابطٍ آخرَ
       التقطها تعبيري النمطيُّ لأنه بلا مرساة. فالدرسُ: **التكافؤُ يُقاس على
       ما يُصيَّر، والمرساةُ رابطُ الشاشةِ لا أوّلُ ذكرٍ لاسمها.**
     ◆ ومعيارُ السجلِّ يقول «على البلاطة» — أي على HTML، فلا يكفي الحاسب. */
$BASE = 'http://localhost/ems';
$JARF = sys_get_temp_dir() . '/' . $MARK . '_jar.txt';
@unlink($JARF);
$req = function ($url, $post = null) use ($JARF) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JARF, CURLOPT_COOKIEFILE => $JARF, CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => $post !== null));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    curl_close($ch);
    return (string) $b;
};
$lp = $req($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $lp, $tk);
/* حسابٌ له نفاذٌ فعليٌّ للشاشتين — و«admin» ليس مستخدمًا (يُردُّ 302 إلى login) */
$req($BASE . '/login.php', array('username' => 'محمد', 'password' => '12345678',
                                 'csrf_token' => isset($tk[1]) ? $tk[1] : ''));
$wsHtml = $req($BASE . '/main/my_workspace.php');
$ibHtml = $req($BASE . '/Portal/approvals_inbox.php');
$ok(strpos($wsHtml, 'مساحة عملي') !== false, 'مساحةُ عملي صُيِّرت (لا تحويلٌ إلى login)',
    'لو رُدَّ تحويلًا لصار الفرعُ يقيس صفحةَ دخولٍ ويخضرُّ خواءً');
$ok(strpos($ibHtml, 'موافقاتي') !== false, 'صندوقُ الموافقاتِ صُيِّر');
$ok(!preg_match('~(Fatal error|Parse error|Uncaught|Undefined (variable|function))~', $wsHtml . $ibHtml),
    'صفرُ انفجارٍ أو متغيّرٍ غيرِ معرَّفٍ في الصفحتين');

$tileNum = null;
if (preg_match('~<a href="\.\./Portal/approvals_inbox\.php".*?</a>~su', $wsHtml, $anchor)) {
    $tileNum = preg_match('~badge[^>]*>\s*([0-9]+)\s*<~u', $anchor[0], $bn) ? (int) $bn[1] : 0;
}
$inboxNum = preg_match('~>\s*([0-9]+)\s*بانتظارك~u', $ibHtml, $ibn) ? (int) $ibn[1] : null;
$ok($tileNum !== null, 'وُجدت بلاطةُ «موافقاتي» بمرساةِ رابطِها');
$ok($inboxNum !== null, 'وُجد رقمُ «بانتظارك» في الصندوق');
$ok($tileNum !== null && $inboxNum !== null && $tileNum === $inboxNum,
    'INJ-0587 على HTML: بلاطةٌ ' . var_export($tileNum, true) . ' = صندوقٌ ' . var_export($inboxNum, true));
/* ⑤ الإفصاحُ عن السقفِ يظهر فعلًا حين يفوق الصادقُ المعروض */
if ($inboxNum !== null && $inboxNum > ems_approvals_inbox_cap()) {
    $ok(preg_match('~يُعرَض\s*([0-9]+)\s*منها~u', $ibHtml) === 1,
        'وأُفصح عن السقفِ لأنَّ الصادقَ (' . $inboxNum . ') يفوق ' . ems_approvals_inbox_cap());
} else {
    $say('  ○ الصادقُ لا يفوق السقفَ اليومَ — فرعُ الإفصاحِ لا يُحكَم عليه');
}
@unlink($JARF);

/* ── كنسٌ ختاميٌّ بعائلةِ الوسم ─────────────────────────────────────────────── */
$conn->query("DELETE FROM approval_links WHERE company_id = {$CO} AND source_ref LIKE '{$FAMILY}%'");
$tail = $conn->affected_rows;
$ok($tail === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $tail . ')');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
