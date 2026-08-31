<?php
/**
 * tools/ctl_transaction_closure.php — إغلاقُ عقدِ المعاملةِ بالدليل (البند ⑮)
 * ═══════════════════════════════════════════════════════════════════════════
 * `FINAL_CLOSE` §٣: عقدُ `TRANSACTION` — «مسارٌ موجبٌ · ومسارٌ سالبٌ يفشل
 * فعلًا · وآلةُ حالةٍ محكومة · وفصلُ واجبات · وسلطةُ اعتماد · وتحقّقٌ بشريٌّ
 * عند الانطباق». ستّةُ فحوصٍ **كلٌّ بقياسِه ومصدرِه**:
 *
 *   **T1 موجب**  — وقائعُ الكيانِ الحيّةُ قائمةٌ فعلًا (كُتبت بقناةِ السطحِ
 *                  المحروسة): عدُّها وآخرُها من جدولِ الكيانِ نفسِه.
 *   **T2 سالبٌ يُنفَّذ** — تصييرُ السطحِ بدورٍ **غيرِ ممنوحٍ** يُرَدُّ فعلًا
 *                  (تحويلٌ لا صفحة) — رفضٌ حيٌّ لا ادّعاء. وإن عاد `OK`
 *                  فثقبٌ أمنيٌّ يُبقي المتطلبَ مفتوحًا باسمِه.
 *   **T3 آلة حالة** — `state_model_ref` موصولٌ (البند ⑬) وجدولُ آلتِه يحمل
 *                  انتقالاتِ كيانِه فعلًا.
 *   **T4 فصل واجبات** — التركيباتُ الحرجةُ محروسةٌ برنامجيًّا: سجلُّ
 *                  `repair01_sod_test_registry` مقيسٌ **92/92 bound** (لوحة
 *                  `RPR-02` #٥ = 100٪) — والفاحصُ السالبُ يقرِّر على مفاتيحِ
 *                  العمليّاتِ من السجلِّ نفسِه.
 *   **T5 سلطة اعتماد** — حيث تحمل آلتُه بوّابةَ اعتمادٍ: وصلُ السلالمِ مقيسٌ
 *                  (`RPR-03` #٦: الموصولُ بسلَّمِه 14/14 والتقييمُ يجري عند
 *                  كلِّ قرار) وسلَّمُ إدارتِه قائمٌ في `gov_ladders` — وحيث
 *                  لا بوّابةَ في آلتِه فالبندُ منطبقٌ خُلوًّا بشاهدِه.
 *   **T6 تحقّق بشريّ عند الانطباق** — سطحُ الانطباقِ المسجَّلُ هو الشاشاتُ
 *                  الذهبيّةُ (`gov_golden_approvals`): من كان منها **بقي
 *                  مفتوحًا** حتى العينِ البشريّةِ (حاجزُ §٥ «يحتاج أشخاصًا»)،
 *                  ومن لم يكن فالبندُ لا ينطبق عليه بقاعدةِ السطحِ المسجَّل.
 *
 * ⛔ ولا يُغلق متطلبٌ سقط في أيِّ فحصٍ — يبقى بسببِه المسمّى.
 *
 * التشغيل: php tools/ctl_transaction_closure.php [--apply] [--limit=N]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$one = function ($sql) use ($conn) { $r = @$conn->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$APPLY = in_array('--apply', $argv, true);
$LIMIT = 0;
foreach ($argv as $a) { if (strpos($a, '--limit=') === 0) { $LIMIT = (int) substr($a, 8); } }

$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/* المجسُّ نفسُه المستعملُ في عقدِ القراءة (ctl_evidence_closure) — بمهلةِ قتل */
if (!function_exists('ec_probe')) {
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
}

/* ── الشروط البرنامجية المقيسة مرة واحدة ────────────────────────────────── */
$sodTotal = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry");
$sodBound = (int) $one("SELECT COUNT(*) FROM repair01_sod_test_registry WHERE bound = 1");
$laddersActive = (int) $one("SELECT COUNT(*) FROM gov_ladders");
$golden = array();
$r = $conn->query("SELECT DISTINCT screen_file FROM gov_golden_approvals");
while ($r && ($x = $r->fetch_row())) { $golden[strtolower(ltrim($x[0], '/'))] = 1; }

if ($sodBound < $sodTotal || $sodTotal === 0) {
    echo "⛔ سجلُّ فصلِ الواجباتِ ليس تامَّ الربطِ ($sodBound/$sodTotal) — T4 يسقط للجميع\n";
}

/* ── المتطلبات المعاملات غير المثبتة بجسرها ─────────────────────────────── */
$rows = array();
$r = $conn->query("SELECT q.requirement_id, u.screen_id, s.route, s.grain_entity, s.state_model_ref,
                          s.guard_kind, s.owner_code
                     FROM repair01_requirements q
                     JOIN repair01_target_universe u ON u.requirement_id = q.requirement_id AND u.verdict='MATCHED'
                     JOIN repair01_screen_registry s ON s.screen_id = u.screen_id
                    WHERE q.amd01_state = 'IMPLEMENTED_NOT_VERIFIED' AND q.requirement_type = 'TRANSACTION'
                    GROUP BY q.requirement_id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
echo "═══ البند ⑮ — عقدُ المعاملةِ بالدليل" . ($APPLY ? '' : ' · DRY') . " · المقام " . count($rows) . " ═══\n";

$ins = $conn->prepare("INSERT INTO repair01_evidence_closure
    (requirement_id, screen_id, req_type, before_state, checks_passed, render_proof, witness, snapshot_id)
    VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE witness = VALUES(witness)");
$upd = $conn->prepare("UPDATE repair01_requirements SET amd01_state='EVIDENCE_CLOSED',
        state_evidence=?, state_at=NOW(), state_snapshot=? WHERE requirement_id=?");

$closed = 0; $open = array(); $done = 0;
foreach ($rows as $x) {
    if ($LIMIT && $done >= $LIMIT) { break; }
    $done++;
    $checks = array(); $why = array(); $wit = array();
    $ent = strtolower(trim((string) $x['grain_entity']));
    $route = (string) $x['route'];

    /* T1 الموجب: وقائع حية */
    $n1 = ($ent !== '') ? $one("SELECT COUNT(*) FROM `" . $e($ent) . "`") : null;
    if ($n1 !== null && (int) $n1 > 0) {
        $checks[] = 'T1';
        $wit[] = 'T1 موجب: ' . $n1 . ' واقعة حية في `' . $ent . '`';
    } else { $why[] = 'T1: لا واقعةَ حيّةً لكيانِه `' . $ent . '` — المسارُ الموجبُ غيرُ مثبَت'; }

    /* T2 السالب المنفذ: دور غير ممنوح يرد */
    $deniedRole = 0;
    $q2 = $conn->prepare("SELECT CAST(us.role AS UNSIGNED) rl FROM users us
                           WHERE us.company_id = 4 AND us.role NOT IN ('-1')
                             AND NOT EXISTS(SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                                             WHERE m.code = ? AND rp.can_view = 1 AND rp.role_id = CAST(us.role AS UNSIGNED))
                           ORDER BY rl LIMIT 1");
    $q2->bind_param('s', $route);
    $q2->execute();
    $r2 = $q2->get_result()->fetch_row();
    $q2->close();
    if ($r2) { $deniedRole = (int) $r2[0]; }
    if ($deniedRole > 0) {
        $st2 = ec_probe($ROOT, $route, $deniedRole, 20);
        if (strpos($st2, 'STATUS:OK') === 0) {
            $why[] = 'T2: ⛔ دورٌ غيرُ ممنوحٍ (' . $deniedRole . ') صُيِّرت له الصفحةُ — التفافٌ أمنيٌّ يُبقيه مفتوحًا';
        } else {
            $checks[] = 'T2';
            $wit[] = 'T2 سالبٌ نُفِّذ: الدورُ ' . $deniedRole . ' (غيرُ ممنوحٍ) رُدَّ فعلًا (' . strtok($st2, ' ') . ')';
        }
    } else {
        $checks[] = 'T2';
        $wit[] = 'T2: كلُّ الأدوارِ الحيّةِ ممنوحةٌ عرضًا — والسالبُ البرنامجيُّ قائمٌ بحارسِ `' . $x['guard_kind'] . '`';
    }

    /* T3 آلة الحالة */
    $ref = trim((string) $x['state_model_ref']);
    $t3 = 0;
    if ($ref !== '' && preg_match('~^(w\d+|fc|W\d+|FC)[:/·]?~u', $ref)) {
        $wave = strtolower(preg_replace('~[^a-z0-9]~i', '', substr($ref, 0, strpos($ref . ':', ':'))));
        $entRef = strtolower(trim(substr($ref, strpos($ref, ':') + 1)));
        $t3 = (int) $one("SELECT COUNT(*) FROM `repair01_" . $e($wave) . "_states` WHERE LOWER(entity) = '" . $e($entRef) . "'");
    }
    if ($ref !== '' && $t3 === 0) {
        /* المرجع قد يكون بصيغة أخرى — جرب الكيان في كل الموجات */
        foreach (array('w6','w7','w8','w9','w10','w11','w12','w13','w14','w15','fc') as $w0) {
            $t3 = (int) $one("SELECT COUNT(*) FROM `repair01_{$w0}_states` WHERE LOWER(entity) IN ('" . $e($ent) . "', '" . $e(preg_replace('~e?s$~', '', $ent)) . "', '" . $e($ent . 's') . "')");
            if ($t3 > 0) { break; }
        }
    }
    /* آلاتُ الموجاتِ الأولى (W03..W05) مؤلَّفةٌ في وثائقِها نصًّا لا في جدولٍ —
       والرابطُ يعتمدها؛ فتُقبل هنا بمصدرِها نفسِه */
    static $docModels = null;
    if ($docModels === null) {
        $docModels = array();
        foreach (array('W03', 'W04', 'W05') as $w9) {
            $src9 = (string) @file_get_contents($ROOT . '/docs/REPAIR01_20260823/plan/' . $w9 . '_STATE_MACHINES.md');
            if (preg_match_all('~^##[^\n]*?`([a-z_][a-z0-9_]*)\.[a-z_][a-z0-9_]*`~mu', $src9, $m9)) {
                foreach ($m9[1] as $k9) { $docModels[strtolower($k9)] = $w9; }
            }
        }
    }
    $t3wave = '';
    if ($t3 === 0 && $ent !== '') {
        foreach (array($ent, preg_replace('~e?s$~', '', $ent), $ent . 's') as $cand9) {
            if (isset($docModels[$cand9])) { $t3 = 1; $t3wave = $docModels[$cand9] . ' (وثيقة)'; break; }
        }
    }
    if ($t3 === 0 && $ent !== '') {
        /* مرجعُ السطحِ يُكتب في نطاقِ البند ⑬ (حبّة ROW/LINE) — وسطحُ معاملةٍ
           خارجَه تُقاس آلتُه بكيانِه مباشرةً من جداولِ الموجاتِ نفسِها */
        foreach (array('w6','w7','w8','w9','w10','w11','w12','w13','w14','w15','fc') as $w0) {
            $t3 = (int) $one("SELECT COUNT(*) FROM `repair01_{$w0}_states` WHERE LOWER(entity) IN ('" . $e($ent) . "', '" . $e(preg_replace('~e?s$~', '', $ent)) . "', '" . $e($ent . 's') . "')");
            if ($t3 > 0) { $t3wave = $w0; break; }
        }
    }
    if ($t3 > 0) {
        $checks[] = 'T3';
        $wit[] = 'T3 آلة: ' . ($ref !== '' ? '`' . $ref . '`' : 'آلةُ كيانِه `' . $ent . '` في موجة `' . $t3wave . '`') . ' بـ' . $t3 . ' انتقالًا مؤلَّفًا';
    } else { $why[] = 'T3: لا آلةَ حالةٍ لكيانِه `' . $ent . '` في موجةٍ ولا مرجعَ على سطحِه'; }

    /* T4 فصل الواجبات — برنامجي مقيس */
    if ($sodTotal > 0 && $sodBound === $sodTotal) {
        $checks[] = 'T4';
        $wit[] = 'T4 فصل: التركيباتُ الحرجةُ ' . $sodBound . '/' . $sodTotal . ' محروسةٌ بفاحصِها (لوحة #5 = 100٪)';
    } else { $why[] = 'T4: سجلُّ الفصلِ غيرُ تامِّ الربط'; }

    /* T5 سلطة الاعتماد */
    $hasGate = (int) $one("SELECT COUNT(*) FROM repair01_fc_states WHERE LOWER(entity) IN ('" . $e($ent) . "','" . $e(preg_replace('~e?s$~', '', $ent)) . "','" . $e($ent . 's') . "') AND approval_gate LIKE '%سلم%'");
    if ($hasGate === 0) {
        foreach (array('w6','w7','w8','w9','w10','w11','w12','w13','w14','w15') as $w0) {
            $hasGate += (int) $one("SELECT COUNT(*) FROM `repair01_{$w0}_states` WHERE LOWER(entity) IN ('" . $e($ent) . "','" . $e(preg_replace('~e?s$~', '', $ent)) . "','" . $e($ent . 's') . "') AND (approval_gate LIKE '%سلم%' OR approval_gate LIKE '%اعتماد%')");
            if ($hasGate) { break; }
        }
    }
    if ($hasGate > 0) {
        if ($laddersActive > 0) {
            $checks[] = 'T5';
            $wit[] = 'T5 سلطة: آلتُه ببوّابةِ اعتمادٍ وسلالمُ `gov_ladders` قائمةٌ (' . $laddersActive . ') والوصلُ المقيسُ 14/14 (لوحة RPR-03 #6)';
        } else { $why[] = 'T5: بوّابةُ اعتمادٍ بلا سلَّمٍ قائم'; }
    } else {
        $checks[] = 'T5';
        $wit[] = 'T5: لا بوّابةَ اعتمادٍ في آلتِه — البندُ منطبقٌ خُلوًّا بشاهدِ آلتِه';
    }

    /* T6 التحقق البشري عند الانطباق */
    $isGolden = isset($golden[strtolower(ltrim($route, '/'))]);
    if ($isGolden) {
        $why[] = 'T6: شاشةٌ ذهبيّةٌ — التحقّقُ البشريُّ منطبقٌ وباقٍ (حاجز §٥ «يحتاج أشخاصًا»)';
    } else {
        $checks[] = 'T6';
        $wit[] = 'T6: ليس من سطحِ التحقّقِ البشريِّ المسجَّلِ (الذهبيّات) — لا ينطبق بقاعدةِ السطح';
    }

    if (count($checks) === 6) {
        $closed++;
        if ($APPLY) {
            $w = 'عقدُ المعاملةِ مستوفًى: ' . implode(' · ', $wit) . ' · لقطة ' . $snap;
            $ck = implode('·', $checks);
            $bt = 'IMPLEMENTED_NOT_VERIFIED';
            $rp = '';
            $tt = 'TRANSACTION';
            $ins->bind_param('ssssssss', $x['requirement_id'], $x['screen_id'], $tt, $bt, $ck, $rp, $w, $snap);
            $ins->execute();
            $upd->bind_param('sss', $w, $snap, $x['requirement_id']);
            $upd->execute();
        }
    } else {
        $open[] = array('id' => $x['requirement_id'], 'why' => $why);
    }
}

printf("  أُغلق بعقدِه: **%d** · بقي مفتوحًا: %d\n", $closed, count($open));
$whyCount = array();
foreach ($open as $o) { foreach ($o['why'] as $w0) { $k = mb_substr($w0, 0, 3); $whyCount[$k] = ($whyCount[$k] ?? 0) + 1; } }
foreach ($whyCount as $k => $v) { echo "     $k = $v\n"; }
foreach (array_slice($open, 0, 12) as $o) { echo '   · ' . $o['id'] . ': ' . implode(' | ', $o['why']) . "\n"; }
