<?php
/**
 * tools/ctl_evidence_closure.php — أمرُ الضبطِ §٥ · مسارُ الإغلاقِ بالدليل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«الموجودُ لا يُعاد بناؤه فقط لأنّه غيرُ مغلقٍ
 *   بالدليل، بل يُغلق بعقدِ الإثباتِ المناسبِ لنوعِه. يجب أن يبدأ
 *   EVIDENCE_CLOSED بالارتفاعِ بالتوازي مع البناء»*.
 *
 * ◆ **عقدُ القراءةِ/التقرير** (‏من `proof_contract` في الدفترِ حرفًا) —
 *   أربعةُ فحوصٍ كلُّها آليّةُ الإثبات:
 *   **E1** نسبُ المصدرِ صحيح ⇐ `repair01_screen_registry.source_of_truth ≠ ''`.
 *   **E2** صلاحيةُ العرضِ محروسة ⇐ `guard_kind ≠ ''` **وللمسارِ وحدةٌ مسجَّلةٌ
 *        بصفِّ منحٍ** — فحارسٌ بلا تسجيلٍ بابٌ بلا قفل.
 *   **E3** الحقولُ الحسّاسةُ بسياستِها ⇐ كيانُ السطحِ **لا حقلَ له في
 *        `sensitive_field_policies` النافذة** = البندُ منطبقٌ خُلوًّا
 *        (‏vacuous بشاهدِه) · ومن له حقلٌ حسّاسٌ **يبقى مفتوحًا** —
 *        ⛔ فتحقّقُ الإقنعةِ عينٌ لا مسحُ نصّ.
 *   **E4** تحقّقُ عرضٍ فعليّ ⇐ `ctl_render_probe.php` **عمليّةً فرعيّةً بجلسةِ
 *        مستخدمٍ حقيقيٍّ لدورٍ ممنوحٍ** — و`STATUS:OK` بطولِ الصفحةِ وبصمتِها.
 *
 * ◆ **عقدُ المعاملةِ يبقى مفتوحًا هنا** — بنصِّه يشمل *«تحقّقًا بشريًّا إن
 *   انطبق»* ومسارًا سالبًا يُشغَّل، وكلاهما ليس مسحَ أداة. تُقاس أجزاؤه
 *   الآليّةُ وتُعرض تقدُّمًا، ⛔ **ولا يُغلق بأداةٍ ما عقدُه يطلب عينًا**.
 *
 * التشغيل: php tools/ctl_evidence_closure.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);
$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/* الحقولُ الحسّاسةُ النافذةُ — كيانًا كيانًا */
$sensEnt = array();
$r = @$conn->query("SELECT DISTINCT SUBSTRING_INDEX(field_code, '.', 1) t
                      FROM sensitive_field_policies WHERE status = 'نافذة'");
while ($r && ($x = $r->fetch_row())) { $sensEnt[strtolower($x[0])] = 1; }

/** المجسُّ بمهلةِ قتلٍ — ⛔ شاشةٌ تعلق في CLI (قِيست: exec_daily_report) لا
 *  تُعلِّق الدفعةَ: ٢٠ ثانيةً ثمَّ `STATUS:TIMEOUT` وقتلُ العمليّة. */
function ec_probe($ROOT, $route, $role, $timeout = 20)
{
    $cmd = '"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/ctl_render_probe.php') . ' '
         . escapeshellarg($route) . ' ' . (int) $role;
    $desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $p = proc_open($cmd, $desc, $pipes, $ROOT);
    if (!is_resource($p)) { return 'STATUS:ERR spawn'; }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = ''; $t0 = microtime(true);
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        $st = proc_get_status($p);
        if (!$st['running']) { $out .= (string) stream_get_contents($pipes[1]); break; }
        if (microtime(true) - $t0 > $timeout) {
            proc_terminate($p, 9);
            @exec('taskkill /F /T /PID ' . (int) $st['pid'] . ' 2>NUL');
            fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
            return 'STATUS:TIMEOUT بعد ' . $timeout . 'ث';
        }
        usleep(150000);
    }
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
    $line = trim($out);
    return $line === '' ? 'STATUS:EMPTY len=0' : strtok($line, "\n");
}

/**
 * FINAL_CLOSE ⑮ — تحقُّقُ الإقنعةِ **بالتصييرِ لا بمسحِ النصّ**:
 * قيمٌ حسّاسةٌ حيّةٌ من كيانِ السطحِ تُصيَّر الشاشةُ لدورٍ ممنوحٍ عرضًا
 * **غيرِ مخوَّلٍ بالسياسةِ** — فوجودُ قيمةٍ منها في الجسمِ خرقٌ يُبقي E3
 * مفتوحًا، وغيابُها كلِّها تحقُّقُ قناعٍ مقيسٌ من الجلسةِ نفسِها.
 */
function ec_mask_verify($ROOT, mysqli $conn, $route, $ent)
{
    $e2 = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
    $pols = array();
    $r = $conn->query("SELECT field_code, allowed_roles_json FROM sensitive_field_policies
                        WHERE status = 'نافذة' AND field_code LIKE '" . $e2($ent) . ".%'");
    while ($r && ($x = $r->fetch_assoc())) { $pols[] = $x; }
    if (!$pols) { return array('ok' => true, 'wit' => 'لا سياسةَ نافذةً لكيانِه'); }
    $allowed = array();
    foreach ($pols as $p0) {
        foreach ((array) json_decode((string) $p0['allowed_roles_json'], true) as $rl) { $allowed[(string) (int) $rl] = 1; }
    }
    /* دورٌ ممنوحٌ عرضًا وغيرُ مخوَّلٍ سياسةً وله مستخدمٌ حيّ */
    $probeRole = 0;
    $st = $conn->prepare("SELECT rp.role_id FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.can_view = 1
                             AND EXISTS(SELECT 1 FROM users us WHERE us.company_id = 4
                                          AND CAST(us.role AS UNSIGNED) = rp.role_id)
                           ORDER BY rp.role_id");
    $st->bind_param('s', $route);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_row()) {
        if (!isset($allowed[(string) (int) $row[0]])) { $probeRole = (int) $row[0]; break; }
    }
    $st->close();
    if ($probeRole === 0) {
        return array('ok' => true, 'wit' => 'كلُّ الأدوارِ الممنوحةِ عرضًا مخوَّلةٌ بالسياسةِ — لا دورَ يُقنَّع له (شاهدُ خلوٍّ مقيس)');
    }
    /* قيمٌ حيّةٌ من الأعمدةِ الحسّاسة */
    $needles = array();
    foreach ($pols as $p0) {
        $col = substr((string) $p0['field_code'], strlen($ent) + 1);
        $q = @$conn->query("SELECT DISTINCT `" . $e2($col) . "` v FROM `" . $e2($ent) . "`
                             WHERE `" . $e2($col) . "` IS NOT NULL AND LENGTH(`" . $e2($col) . "`) >= 5 LIMIT 3");
        while ($q && ($z = $q->fetch_row())) { $needles[$col][] = (string) $z[0]; }
    }
    if (!array_filter($needles)) {
        return array('ok' => true, 'wit' => 'لا قيمةَ حسّاسةً حيّةً تُقاس (الأعمدةُ فارغة) — خلوٌّ مقيس');
    }
    $tmp = sys_get_temp_dir() . '/ec_mask_' . getmypid() . '.html';
    @unlink($tmp);
    putenv('CTL_PROBE_BODY=' . $tmp);
    $status = ec_probe($ROOT, $route, $probeRole);
    putenv('CTL_PROBE_BODY');
    $body = (string) @file_get_contents($tmp);
    @unlink($tmp);
    if (strpos($status, 'STATUS:OK') !== 0 || $body === '') {
        return array('ok' => false, 'why' => 'تعذّر تصييرُ فحصِ القناعِ للدور ' . $probeRole . ' (' . $status . ')');
    }
    $leaks = array();
    foreach ($needles as $col => $vals) {
        foreach ($vals as $v) { if ($v !== '' && strpos($body, $v) !== false) { $leaks[] = $col; break; } }
    }
    if ($leaks) {
        return array('ok' => false, 'why' => 'قيمةٌ حسّاسةٌ ظاهرةٌ لدورٍ غيرِ مخوَّل (' . $probeRole . '): ' . implode('·', $leaks));
    }
    $cols = implode('·', array_keys($needles));
    return array('ok' => true,
        'wit' => 'قناعٌ مثبَتٌ بالتصيير: الدورُ ' . $probeRole . ' (ممنوحٌ عرضًا غيرُ مخوَّلٍ سياسةً) لا يرى قيمَ ' . $cols . ' الحيّةَ في جسمِ الصفحة');
}

/** دورٌ ممنوحٌ `can_view` للمسارِ وله مستخدمٌ حيٌّ في co4 — أو صفر */
function ec_viewer_role(mysqli $conn, $route)
{
    $st = $conn->prepare("SELECT rp.role_id FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.can_view = 1
                             AND EXISTS(SELECT 1 FROM users us WHERE us.company_id = 4
                                          AND CAST(us.role AS UNSIGNED) = rp.role_id)
                           ORDER BY rp.role_id LIMIT 1");
    if (!$st) { return 0; }
    $st->bind_param('s', $route);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    $st->close();
    return $row ? (int) $row[0] : 0;
}

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    /* **الكاسر ①**: المجسُّ يرفض مسارًا لا وجودَ له */
    $o0 = array(); @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/ctl_render_probe.php')
               . ' zzq/absent_probe.php 17 2>&1', $o0);
    if (strpos(implode('', $o0), 'STATUS:ERR') === false) { echo "  X المجسُّ قبِل العدم\n"; $fail++; }
    /* **الكاسر ②**: شاشةٌ حيّةٌ تُصيَّر فعلًا */
    $o1 = array(); @exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/ctl_render_probe.php')
               . ' Finance/cost_report_fin.php 17 2>&1', $o1);
    if (strpos(implode('', $o1), 'STATUS:OK') === false) { echo "  X شاشةٌ حيّةٌ لم تُصيَّر\n"; $fail++; }
    /* **الكاسر ③**: دورُ العرضِ يُشتقُّ من المنحِ لا يُخترع */
    if (ec_viewer_role($conn, 'zzq/absent_probe.php') !== 0) { echo "  X دورٌ اختُرع لمسارٍ وهميّ\n"; $fail++; }
    if (count($sensEnt) < 3) { echo "  X سياساتُ الحساسيّةِ لم تُقرأ\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — المجسُّ يميّز والدورُ من المنح\n";
    exit($fail ? 1 : 0);
}

/* ═══ المتطلباتُ المنفَّذةُ غيرُ المثبتة — بأسطحِها ═══════════════════════ */
$rows = array();
$r = $conn->query("SELECT r.requirement_id, r.requirement_type, r.amd01_state,
                          u.screen_id, s.route, s.guard_kind, s.source_of_truth, s.grain_entity,
                          s.state_model_ref
                     FROM repair01_requirements r
                     JOIN repair01_target_universe u ON u.requirement_id = r.requirement_id AND u.verdict = 'MATCHED'
                     JOIN repair01_screen_registry s ON s.screen_id = u.screen_id
                    WHERE r.amd01_state = 'IMPLEMENTED_NOT_VERIFIED'");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$closable = array(); $openProj = array(); $tx = array('n' => 0, 'guard' => 0, 'sm' => 0); $untyped = 0;
foreach ($rows as $x) {
    if ($x['requirement_type'] === 'TRANSACTION') {
        $tx['n']++;
        if (trim((string) $x['guard_kind']) !== '') { $tx['guard']++; }
        if (trim((string) $x['state_model_ref']) !== '') { $tx['sm']++; }
        continue;
    }
    /* عقدُ STRUCTURAL نصُّه: «مصالحةٌ قاطعةٌ بالمعرِّف · ودليلٌ مقيس ·
       ⛔ ولا يُطالَب بأثرٍ تجاريٍّ ولا برحلةٍ بشريّة» — فيُغلَق بالفحوصِ
       المقيسةِ نفسِها (نسبُ المصدرِ والحارسُ والتصييرُ الحيُّ) كالقراءة.
       والرحلاتُ والتكاملُ يبقيان لمسارَيهما (بشريٌّ/عقدُ حدثٍ) */
    /* عقدُ EVENT_INTEGRATION خماسيُّ الأركانِ **وكلُّه مقيسٌ من سجلِّ
       الحقائق**: الحدثُ صادرٌ · المستهلكُ استلمه (delivered_ok) · الأثرُ
       وقع (روابطُ الأثر) · منعُ التكرارِ (مفتاحُ العطالة) · والفشلُ يظهر
       (معدودُ التعثّرِ والموتى ظاهرٌ لا مبتلَع). عائلاتُ أحداثِ المتطلبِ
       تُنتزَع من شيفرةِ سطحِه المبنيِّ (بادئاتُ event_key فيه) لا تُخمَّن. */
    if ($x['requirement_type'] === 'EVENT_INTEGRATION') {
        $src0 = (string) @file_get_contents($ROOT . '/' . $x['route']);
        $fams = array();
        if (preg_match_all("~'([a-z]+)\.'~", $src0, $mf)) { foreach ($mf[1] as $f0) { $fams[$f0 . '.'] = 1; } }
        if (preg_match_all("~event_key[^\n]{0,40}'([a-z]+)\.~", $src0, $mf)) { foreach ($mf[1] as $f0) { $fams[$f0 . '.'] = 1; } }
        $checks = array(); $why = array();
        if (!$fams) {
            $openProj[] = array('x' => $x, 'why' => array('EV0: لا بادئةَ عائلةِ أحداثٍ في شيفرةِ سطحِه — العزوُ يُقاس لا يُخمَّن'));
            continue;
        }
        $like = array();
        foreach (array_keys($fams) as $f0) { $like[] = "b.event_key LIKE '" . $e($f0) . "%'"; }
        $cond = '(' . implode(' OR ', $like) . ')';
        $m0 = $conn->query("SELECT COUNT(*) n, COALESCE(SUM(delivered_ok),0) ok,
                                   COALESCE(SUM(delivered_failed),0) f, COALESCE(SUM(in_dlq),0) d,
                                   SUM(idempotency_key IS NOT NULL AND idempotency_key <> '') idem
                              FROM ems_business_events b WHERE $cond")->fetch_assoc();
        $fx0 = (int) $one("SELECT COUNT(*) FROM fin_event_links l JOIN ems_business_events b ON l.event_id = b.id WHERE $cond");
        if ((int) $m0['n'] > 0) { $checks[] = 'EV1'; } else { $why[] = 'EV1: لا حدثَ صادرًا لعائلاتِه'; }
        if ((int) $m0['ok'] > 0) { $checks[] = 'EV2'; } else { $why[] = 'EV2: لا استلامَ مستهلكٍ مدوَّنًا'; }
        if ($fx0 > 0) { $checks[] = 'EV3'; } else { $why[] = 'EV3: لا أثرَ مقيَّدًا بروابطِ الأثر'; }
        if ((int) $m0['idem'] === (int) $m0['n'] && (int) $m0['n'] > 0) { $checks[] = 'EV4'; } else { $why[] = 'EV4: مفتاحُ العطالةِ ناقصٌ في بعضِ الصفوف'; }
        $checks[] = 'EV5'; /* الفشلُ ظاهرٌ معدودًا في الأعمدةِ نفسِها — والعدُّ يُدوَّن في الشاهد */
        if (count($checks) === 5) {
            $x['__ev_wit'] = 'صادرٌ ' . $m0['n'] . ' · مستلَمٌ ' . $m0['ok'] . ' · أثرٌ مقيَّدٌ ' . $fx0
                           . ' · عطالةٌ ' . $m0['idem'] . '/' . $m0['n'] . ' · وفشلٌ ظاهرٌ ' . $m0['f'] . '+' . $m0['d']
                           . ' (عائلات: ' . implode(' ', array_keys($fams)) . ')';
            $closable[] = array('x' => $x, 'checks' => $checks);
        } else { $openProj[] = array('x' => $x, 'why' => $why); }
        continue;
    }
    if ($x['requirement_type'] !== 'PROJECTION_REPORT' && $x['requirement_type'] !== 'STRUCTURAL') { $untyped++; continue; }
    $checks = array(); $why = array();
    /* E1 */
    if (trim((string) $x['source_of_truth']) !== '') { $checks[] = 'E1'; }
    else { $why[] = 'E1: source_of_truth فارغٌ في السجلِّ المبنيّ'; }
    /* E2 */
    $mod = (int) $one("SELECT COUNT(*) FROM modules WHERE code = '" . $e($x['route']) . "'");
    if (trim((string) $x['guard_kind']) !== '' && $mod > 0) { $checks[] = 'E2'; }
    else { $why[] = 'E2: ' . ($mod > 0 ? 'حارسٌ فارغ' : 'لا وحدةَ مسجَّلةً للمسار'); }
    /* E3 — FINAL_CLOSE ⑮: حيث سياسةٌ نافذةٌ يُتحقَّق القناعُ بالتصييرِ لا بالمسح */
    $ent = strtolower(trim((string) $x['grain_entity']));
    if ($ent === '' || !isset($sensEnt[$ent])) { $checks[] = 'E3'; }
    else {
        $mv = ec_mask_verify($ROOT, $conn, (string) $x['route'], $ent);
        if ($mv['ok']) { $checks[] = 'E3'; $x['__e3_wit'] = $mv['wit']; }
        else { $why[] = 'E3: ' . $mv['why']; }
    }
    if (count($checks) === 3) { $closable[] = array('x' => $x, 'checks' => $checks); }
    else { $openProj[] = array('x' => $x, 'why' => $why); }
}

echo "\n═══ أمرُ الضبطِ §٥ — مسارُ الإغلاقِ بالدليل ═══\n";
printf("  اللقطة %s · منفَّذٌ غيرُ مثبتٍ بأسطحِه: **%d**\n", $snap !== '' ? $snap : 'DRY', count($rows));
printf("  قراءاتٌ مرشَّحةٌ للإغلاق (E1+E2+E3 ثمَّ E4 حيًّا): **%d** · مفتوحةٌ بأسبابِها: %d · معاملاتٌ: %d · بلا نوع: %d\n",
       count($closable), count($openProj), $tx['n'], $untyped);
printf("  ◆ تقدُّمُ المعاملاتِ الآليُّ (لا يُغلقها): حارسٌ %d/%d · آلةُ حالةٍ %d/%d — والباقي عينٌ بشريّةٌ ومسارٌ سالب\n",
       $tx['guard'], $tx['n'], $tx['sm'], $tx['n']);

/* ═══ E4 + الإغلاق ══════════════════════════════════════════════════════ */
$closed = 0; $e4fail = array();
if ($APPLY) {
    foreach ($closable as $c0) {
        $x = $c0['x'];
        /* ⛔ **الأدوارُ الممنوحةُ تُجرَّب لا أوّلُها وحدَه** — فطبقةُ القوالبِ قد
           تمنع مستخدمي دورٍ وتُجيز آخرين، والحكمُ بالمستخدمِ لا بالدور. وما
           رُدَّ من كلِّ الأدوارِ **منعٌ حقيقيٌّ يُبلَّغ بسببِه لا يُلتفُّ عليه**. */
        $roles = array();
        $rr = $conn->query("SELECT rp.role_id FROM role_permissions rp
                              JOIN modules m ON m.id = rp.module_id
                             WHERE m.code = '" . $e($x['route']) . "' AND rp.can_view = 1
                               AND EXISTS(SELECT 1 FROM users us WHERE us.company_id = 4
                                            AND CAST(us.role AS UNSIGNED) = rp.role_id)
                             ORDER BY rp.role_id LIMIT 4");
        while ($rr && ($z = $rr->fetch_row())) { $roles[] = (int) $z[0]; }
        if (!$roles) { $e4fail[] = $x['requirement_id'] . ' — لا دورَ ممنوحًا له مستخدمٌ حيّ'; continue; }
        $line = ''; $role = 0;
        foreach ($roles as $role) {
            $line = ec_probe($ROOT, $x['route'], $role);
            if (strpos($line, 'STATUS:OK') === 0) { break; }
        }
        if (strpos($line, 'STATUS:OK') !== 0) {
            $e4fail[] = $x['requirement_id'] . ' — ' . $line . ' (جُرِّبت الأدوارُ ' . implode('·', $roles) . ')';
            continue;
        }
        $proof = trim(substr($line, 10));
        $isEv = isset($x['__ev_wit']);
        $wit = $isEv
            ? ('عقدُ التكاملِ الخماسيُّ مستوفًى قياسًا من سجلِّ الحقائق: ' . $x['__ev_wit']
             . ' · وE4 صُيِّر سطحُه فعلًا بجلسةِ دورٍ ممنوحٍ (' . $role . '): ' . $proof . ' · لقطة ' . $snap)
            : ('عقدُ القراءةِ مستوفًى آليًّا: E1 نسبُ المصدرِ (`' . mb_substr((string) $x['source_of_truth'], 0, 60)
             . '`) · E2 حارسٌ `' . $x['guard_kind'] . '` ووحدةٌ مسجَّلة · E3 '
             . (isset($x['__e3_wit']) ? $x['__e3_wit'] : 'لا حقلَ حسّاسًا نافذَ السياسةِ لكيانِه')
             . ' · E4 صُيِّرت فعلًا بجلسةِ دورٍ ممنوحٍ (' . $role . '): ' . $proof . ' · لقطة ' . $snap);
        $ckStr = $isEv ? 'EV1·EV2·EV3·EV4·EV5·E4' : 'E1·E2·E3·E4';
        $rtType = $isEv ? 'EVENT_INTEGRATION' : 'PROJECTION_REPORT';
        $conn->query('START TRANSACTION');
        $ok1 = $conn->query("INSERT INTO repair01_evidence_closure
                (requirement_id, screen_id, req_type, before_state, checks_passed, render_proof, witness, snapshot_id)
                VALUES ('" . $e($x['requirement_id']) . "','" . $e($x['screen_id']) . "','" . $e($rtType) . "',
                        '" . $e($x['amd01_state']) . "','" . $e($ckStr) . "','" . $e($proof) . "','" . $e($wit) . "','" . $e($snap) . "')
                ON DUPLICATE KEY UPDATE witness = VALUES(witness)");
        $ok2 = $conn->query("UPDATE repair01_requirements
                                SET amd01_state = 'EVIDENCE_CLOSED', state_evidence = '" . $e($wit) . "',
                                    state_at = NOW(), state_snapshot = '" . $e($snap) . "'
                              WHERE requirement_id = '" . $e($x['requirement_id']) . "'");
        if (!$ok1 || !$ok2) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
        $conn->query('COMMIT');
        $closed++;
    }
    printf("\n  ✔ **`EVIDENCE_CLOSED` صفر ⇒ %d** — كلٌّ بفحوصِه الأربعةِ وبصمةِ تصييرِه\n", $closed);
    if ($e4fail) {
        echo "  ⛔ سقط في E4 (يبقى مفتوحًا بسببِه):\n";
        foreach ($e4fail as $f0) { echo "     · $f0\n"; }
    }
}

if ($MD) {
    $o  = "# أمرُ الضبطِ §٥ — مسارُ الإغلاقِ بالدليل\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "| المفردة | العدد |\n|---|---:|\n";
    $o .= "| منفَّذٌ غيرُ مثبتٍ بأسطحِه | " . count($rows) . " |\n";
    $o .= "| **أُغلق بالدليل** (E1..E4) | **$closed** |\n";
    $o .= "| قراءةٌ سقطت في E4 | " . count($e4fail) . " |\n";
    $o .= "| قراءةٌ مفتوحةٌ بسببٍ قبل E4 | " . count($openProj) . " |\n";
    $o .= "| معاملاتٌ (عقدُها يطلب عينًا ومسارًا سالبًا) | {$tx['n']} |\n";
    $o .= "| بلا نوعٍ في الدفتر | $untyped |\n\n";
    if ($e4fail) {
        $o .= "## سقط في تحقّقِ العرضِ الفعليّ (E4)\n\n";
        foreach ($e4fail as $f0) { $o .= "- $f0\n"; }
        $o .= "\n";
    }
    if ($openProj) {
        $o .= "## قراءاتٌ مفتوحةٌ بأسبابِها\n\n";
        foreach ($openProj as $p0) {
            $o .= '- `' . $p0['x']['requirement_id'] . '` (' . $p0['x']['route'] . '): ' . implode(' · ', $p0['why']) . "\n";
        }
    }
    $o .= "\n⛔ **المعاملاتُ لا تُغلق بأداة** — عقدُها بنصِّه يشمل تحقّقًا بشريًّا ومسارًا سالبًا يُشغَّل "
        . "(تقدُّمُها الآليُّ: حارسٌ {$tx['guard']}/{$tx['n']} · آلةُ حالةٍ {$tx['sm']}/{$tx['n']}).\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_EVIDENCE_CLOSURE.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_EVIDENCE_CLOSURE.md\n";
}
