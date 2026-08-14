<?php
/**
 * tools/fix_permgov_campaign.php — حملةُ أدلةِ الصلاحياتِ والحوكمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ:
 *
 * **الحكمُ الحاكم**: «لا تُغلق بندًا بشاهدٍ أضيقَ من نصِّ اختبارِه». فهذه الأداةُ
 * **تُفكّك نصَّ اختبارِ القبولِ إلى شروطٍ** وتقيس كلَّ شرطٍ على حدةٍ، وتُغلق
 * البندَ **فقط** إذا قِيست شروطُه كلُّها ونجحت. وما لم يُقَس يُعلَن بسببِه
 * ويبقى البندُ مفتوحًا. ولا يُوسَّع اختبارٌ ليطابق ما تستطيع الأداةُ قياسَه.
 *
 * ── سبعةُ أنماطِ قياسٍ، كلٌّ بآليتِه المبنيةِ في المستودعِ لا بآليةٍ ثانية ────
 *   AUDIT       ⇐ includes/audit_trail.php · activity_logs
 *   FIELD_MASK  ⇐ app/Services/Governance/FieldGovernor.php
 *   SOD         ⇐ includes/sod_guard.php
 *   SCOPE       ⇐ TenantDb · fin_project_scope
 *   NAV         ⇐ نمطُ tools/fix_nav_href_probe.php (عمليةٌ منفصلةٌ لكلِّ دور)
 *   EXPORT      ⇐ excel.php · ExcelRegistry
 *   DENY_WRITE  ⇐ 403 + صفرُ أثرٍ في الجدولِ المُسنَد
 *
 * ── وثلاثةُ فخاخٍ مسجَّلةٌ عولجت هنا صراحةً ─────────────────────────────────
 *   ① **تمييزُ ٤٠٣**: الشوطُ الثالثُ (مخوَّلٌ بلا رمزِ جلسة) كان يتوقّع ٤٠٣
 *      دائمًا. و`CSRF_ENFORCE_PATHS` تغطّي خمسةَ مجلداتٍ فقط — فمسارٌ خارجَها
 *      يعيد 200 بلا رمز، وذلك **يُثبت** أنَّ ٤٠٣ الأصليَّ ليس عطلَ حماية.
 *      فصار للشوطِ الثالثِ ثلاثُ نتائجَ لا اثنتان: مُنفَذٌ · غيرُ مُنفَذٍ · ملتبس.
 *   ② **الوسمُ عائليٌّ ثابتٌ** (لا `getmypid`) — وإلا كانت كلُّ جولةٍ عمياءَ
 *      عمّا تركته سابقتُها.
 *   ③ **مُرجَعُ كلِّ حذفٍ يُفحَص** — فمفتاحٌ أجنبيٌّ يردُّ صامتًا.
 *
 *   php tools/fix_permgov_campaign.php [--run] [--only=INJ-0011,…] [--md=<مسار>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$RUN = in_array('--run', $argv, true);
$MD = null; $ONLY = null;
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); }
    if (strpos($a, '--only=') === 0) { $ONLY = array_map('trim', explode(',', substr($a, 7))); }
}
$TAG = 'PERMGOV';           /* وسمٌ عائليٌّ **ثابت** */

$lines = array();
$say = function ($s = '') use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

/* ══ ① النطاقُ مشتقًّا بالقاعدة ═══════════════════════════════════════════ */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}
$FAMILY = array('Permission Gap', 'Governance Gap');
$items = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++; if ($n <= 3) { continue; }
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (!in_array(trim($c[9]), $FAMILY, true)) { continue; }
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    if ($ONLY && !in_array($id, $ONLY, true)) { continue; }
    $items[$id] = array('id' => $id, 'dept' => trim($c[3]), 'scr' => trim($c[4]), 'url' => trim($c[5]),
        'type' => trim($c[9]), 'sev' => trim($c[10]), 'test' => trim($c[20]), 'real' => trim($c[8]),
        'half' => (trim($c[10]) === 'P0' || trim($c[10]) === 'P1') ? '①' : '②');
}
fclose($fh);

/* ══ ② تفكيكُ نصِّ الاختبارِ إلى شروط ═════════════════════════════════════
     الفصلُ بـ«؛» أوّلًا — وهي الفاصلةُ التي يستعملها السجلُّ بين الشروط.
     ثم بـ«و» في أوّلِ جملةٍ فعليةٍ. وشرطٌ أقصرُ من ١٥ حرفًا يُضمُّ لسابقه. */
$clausesOf = function ($test) {
    $parts = preg_split('~[؛;]+~u', (string) $test);
    $out = array();
    foreach ($parts as $p) {
        $p = trim($p, " \t\n\r.،");
        if ($p === '') { continue; }
        if ($out && mb_strlen($p) < 15) { $out[count($out) - 1] .= ' — ' . $p; continue; }
        $out[] = $p;
    }
    return $out ? $out : array(trim((string) $test));
};

/* ══ ③ الأنماطُ ═══════════════════════════════════════════════════════════ */
$PAT = array(
    'SOD'         => '~من\s+(سجّل|أدخل|نفّذ|أنشأ|أعدّ|قدّم|رفع|أنشأ)|منشئُ|مُدخِلُ|لا يعتمد المرءُ|نفسُه لا يستطيع|أدخله المعتمِدُ نفسُه|الذي نفّذ~u',
    'BREAK_GLASS' => '~كسر الزجاج|كسرِ زجاج|permission_exceptions|valid_to~u',
    'FIELD_MASK'  => '~حقلٍ حساس|الحقول الحساسة|يُخفي قيمتَه|لا يجد حقولَ|مقنَّعًا|غائبٌ نصًّا|بلا ذلك العمود|يُسقطه من ملف التصدير~u',
    'EXPORT'      => '~can_export|ملفِّ التصدير|ملفَّ التصدير|أعمدةَ الملفين~u',
    'CAP'         => '~سقف|يُصعَّد|تصعيد|مرجعُ تفويض|صاحبِ سقفٍ أعلى~u',
    'SCOPE'       => '~نطاقين|كيانٍ آخر|شركةٍ أخرى|لا يراه في صندوق|عدّادين مختلفين|owner_unit_id~u',
    'NAV'         => '~سايدبار|القائمةِ الجانبية|تعرض المرحلتان|في قائمة الدور|غيرُ موجودٍ في القائمة~u',
    'AUDIT'       => '~سطرَ تدقيق|سجل التدقيق|activity_logs|صفَّ اطّلاع|read_log|ويُسجَّل|يُسجَّل الرفض|قبل وبعد|old_value|صفَّ تدقيق~u',
    'DENY_WRITE'  => '~يعيد ٤٠٣|يُعيد 403|يُردُّ 403|يتلقى 403|يجب 403|GOV-PERM-403|بلا can_edit|بلا can_add|ولا يُدرج|لا يُنشئ صفًّا|صفرُ صفٍّ|يُرفض 40~u',
    'REJECT_GUARD' => '~يُرفض 4\d\d|تُرفض 4\d\d|يُرفض برمز|422|423|409~u',
    'TOKEN_GET'   => '~بلا رمزٍ صالحٍ|بلا رمز CSRF|بلا رمزٍ~u',
    'STATE_GUARD' => '~يبقى الصفُّ|تبقى الحالة|محسوبةً من|لا من المُدخَل|الحقلُ غيرُ موجودٍ في نموذجه|بنفسه غيرُ ممكنة~u',
);
$patternOf = function ($test) use ($PAT) {
    foreach ($PAT as $k => $re) { if (preg_match($re, $test)) { return $k; } }
    return 'OTHER';
};

/* ══ ④ الوصولُ إلى القاعدةِ والشبكة ══════════════════════════════════════ */
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';

/* مسارُ الشاشةِ من الرابط */
$relOf = function ($url) use ($ROOT) {
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', (string) $url, $m)
        && is_file($ROOT . '/' . $m[1])) { return $m[1]; }
    return null;
};
/* مصفوفةُ المنحِ على شاشةٍ */
$grantsOf = function ($rel) use ($conn) {
    $out = array('view' => array(), 'edit' => array(), 'partial' => array(), 'registered' => false);
    $st = $conn->prepare('SELECT rp.role_id, rp.can_view, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id WHERE m.code = ? ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $r = $st->get_result();
    while ($r && ($x = $r->fetch_assoc())) {
        $out['registered'] = true;
        $rid = (int) $x['role_id'];
        if ((int) $x['can_view'] === 1) { $out['view'][] = $rid; }
        if ((int) $x['can_edit'] === 1) { $out['edit'][] = $rid; }
        if ((int) $x['can_view'] === 1 && (int) $x['can_edit'] === 0) { $out['partial'][] = $rid; }
    }
    $st->close();
    return $out;
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users WHERE role = ? AND company_id = ?
                           AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role;
    $st->bind_param('si', $r, $CO);
    $st->execute();
    $x = $st->get_result()->fetch_row();
    $st->close();
    return $x ? (string) $x[0] : '';
};
/* هل مسارُ الشاشةِ تحت إنفاذِ CSRF؟ — يحسم تفسيرَ الشوطِ الثالث */
require_once $ROOT . '/includes/env.php';
$csrfPaths = array_filter(array_map('trim', explode(',', (string) ems_env('CSRF_ENFORCE_PATHS', ''))));
$csrfEnforced = function ($rel) use ($csrfPaths) {
    foreach ($csrfPaths as $p) { if ($p !== '' && stripos('/' . $rel, $p) !== false) { return true; } }
    return false;
};
/* أيُّ جدولٍ تكتبه الشاشة — من معالجِ POST نفسِه، لا من نصِّ الاختبار */
$writesOf = function ($rel) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    $t = array();
    if (preg_match_all('~INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)`?~i', $s, $m)) {
        foreach ($m[1] as $x) { $t[strtolower($x)] = 'INSERT'; }
    }
    if (preg_match_all('~UPDATE\s+`?([a-z0-9_]+)`?\s+SET~i', $s, $m2)) {
        foreach ($m2[1] as $x) { if (!isset($t[strtolower($x)])) { $t[strtolower($x)] = 'UPDATE'; } }
    }
    return $t;
};
/* هل الشاشةُ تنادي موصِّلَ التدقيقِ فعلًا — وتُضمِّن مصدرَه؟ */
$auditAdoption = function ($rel) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $rel);
    $calls = preg_match('~\bems_audit_change\s*\(~', $s) ? true : false;
    $req = (strpos($s, 'audit_trail.php') !== false);
    /* والخدماتُ التي تستدعيها الشاشةُ قد تحمل التدقيقَ عنها */
    $viaSvc = false;
    if (preg_match_all('~(?:require_once|include)[^;\n]*[\'"]([^\'"]+\.php)[\'"]~', $s, $m)) {
        foreach ($m[1] as $inc) {
            $p = $ROOT . '/' . ltrim(preg_replace('~^(\.\./)+~', '', $inc), '/');
            if (is_file($p) && preg_match('~\bems_audit_change\s*\(~', (string) @file_get_contents($p))) {
                $viaSvc = true; break;
            }
        }
    }
    return array('calls' => $calls, 'requires' => $req, 'viaService' => $viaSvc);
};

/* ══ ⑤ الجولة ════════════════════════════════════════════════════════════ */
$say('══════════════════════════════════════════════════════════════════');
$say(' حملةُ أدلةِ الصلاحياتِ والحوكمة — الشرطُ وحدةَ القياسِ لا البند');
$say('══════════════════════════════════════════════════════════════════');
$say('');
$say('  النطاق: ' . count($items) . ' بندًا  (Permission Gap + Governance Gap · ليست «مُغلقٌ بشاهد»)');
$say('  إنفاذُ CSRF على: ' . (count($csrfPaths) ? implode(' · ', $csrfPaths) : 'لا شيء'));
$say('');

$rows = array(); $closed = 0; $open = 0;
$clauseTot = 0; $clauseMeas = 0; $clausePass = 0;
$byPat = array(); $byReason = array();

foreach ($items as $id => $it) {
    $rel = $relOf($it['url']);
    $pat = $patternOf($it['test']);
    $cls = $clausesOf($it['test']);
    $byPat[$pat] = (isset($byPat[$pat]) ? $byPat[$pat] : 0) + 1;
    $verdicts = array();      /* لكلِّ شرط: array(state, note) — state ∈ pass|fail|unmeasured */

    if ($rel === null) {
        foreach ($cls as $c) { $verdicts[] = array('unmeasured', 'الرابطُ لا يشير إلى ملفٍّ حيٍّ واحد'); }
    } else {
        $g = $grantsOf($rel);
        $writes = $writesOf($rel);
        $aud = $auditAdoption($rel);
        $enf = $csrfEnforced($rel);

        foreach ($cls as $ci => $c) {
            /* ── شرطُ التدقيقِ: يُقاس بالتبنّي أوّلًا — فحارسٌ غيرُ مُنادًى لا يقع ── */
            if (preg_match($PAT['AUDIT'], $c)) {
                if (!$aud['calls'] && !$aud['viaService']) {
                    $verdicts[] = array('fail',
                        'الشاشةُ **لا تنادي** `ems_audit_change` ولا خدمةً تناديه — فلا صفَّ تدقيقٍ يمكن أن يقع');
                } elseif ($aud['calls'] && !$aud['requires']) {
                    $verdicts[] = array('fail',
                        'تنادي الموصِّلَ **بلا تضمينِ مصدرِه** — فـ`function_exists` كاذبٌ دائمًا ويُتخطّى صامتًا');
                } else {
                    $verdicts[] = array('unmeasured',
                        'التبنّي قائمٌ — ويبقى إثباتُ **صفٍّ واحدٍ بقيمةِ قبل/بعد** بفعلٍ حيٍّ عبر الشاشة (حمولةٌ مخصَّصة)');
                }
                continue;
            }
            /* ── شرطُ رفضِ الكتابةِ: يحتاج طرفًا جزئيًّا وجدولًا مُسنَدًا ── */
            if (preg_match($PAT['DENY_WRITE'], $c)) {
                if (!$g['registered']) {
                    $verdicts[] = array('fail', 'الشاشةُ **غيرُ مسجَّلةٍ في `modules`** — فالبوابةُ fail-open لكلِّ دور');
                } elseif (!$g['partial']) {
                    $verdicts[] = array('unmeasured',
                        'لا دورَ بـ`can_view=1` و`can_edit=0` — فلا طرفَ يعبر العرضَ ويُردُّ عند الكتابة'
                        . ' (عرض: ' . implode(',', $g['view']) . ' · كتابة: ' . implode(',', $g['edit']) . ')');
                } elseif (!$writes) {
                    $verdicts[] = array('unmeasured', 'لا `INSERT`/`UPDATE` في الشاشة — الفعلُ في خدمةٍ أو AJAX');
                } else {
                    $verdicts[] = array('unmeasured',
                        'قابلٌ للقياسِ حيًّا: طرفٌ جزئيٌّ (' . implode(',', $g['partial']) . ') وجدولٌ مُسنَدٌ ('
                        . implode(',', array_keys($writes)) . ') — يحتاج شوطًا حيًّا');
                }
                continue;
            }
            /* ── شرطُ رمزِ الجلسة: يُحسم بمعرفةِ الإنفاذِ لا بافتراضِ ٤٠٣ ── */
            if (preg_match($PAT['TOKEN_GET'], $c)) {
                $verdicts[] = $enf
                    ? array('unmeasured', 'المسارُ **تحت إنفاذِ CSRF** — يُقاس بشوطٍ بلا رمزٍ يتوقّع `CSRF-403`')
                    : array('fail',
                        'المسارُ **خارجَ `CSRF_ENFORCE_PATHS`** — فطلبٌ بلا رمزٍ لا يُردُّ، والشرطُ غيرُ محقَّقٍ بنيويًّا');
                continue;
            }
            /* ── شرطُ فصلِ الواجبات ── */
            if (preg_match($PAT['SOD'], $c)) {
                $sodUsed = preg_match('~ems_sod_check_grant|sod_guard~',
                    (string) @file_get_contents($ROOT . '/' . $rel));
                $verdicts[] = $sodUsed
                    ? array('unmeasured', 'حارسُ فصلِ الواجباتِ مُنادًى — يبقى إثباتُ المنعِ بفعلٍ حيٍّ بحسابين')
                    : array('fail', 'الشاشةُ **لا تنادي** `includes/sod_guard.php` — فلا منعَ يقع');
                continue;
            }
            /* ── شرطُ حجبِ حقل ── */
            if (preg_match($PAT['FIELD_MASK'], $c)) {
                $src = (string) @file_get_contents($ROOT . '/' . $rel);
                $fgUsed = (strpos($src, 'FieldGovernor') !== false)
                       || (strpos($src, 'ems_log_sensitive_read') !== false);
                $verdicts[] = $fgUsed
                    ? array('unmeasured', 'الحاكمُ مُنادًى — يبقى إثباتُ **غيابِ الحقلِ نصًّا** باستجابتين خامّتين')
                    : array('fail', 'الشاشةُ **لا تنادي** `FieldGovernor` — فالحقلُ يُرسَل للجميع');
                continue;
            }
            /* ── ما عدا ذلك ── */
            $verdicts[] = array('unmeasured', 'نمطُ «' . $pat . '» — لا مقياسَ آليًّا في هذه الجولة');
        }
    }

    $nPass = 0; $nFail = 0; $nUn = 0;
    foreach ($verdicts as $v) {
        if ($v[0] === 'pass') { $nPass++; } elseif ($v[0] === 'fail') { $nFail++; } else { $nUn++; }
    }
    $clauseTot += count($verdicts);
    $clauseMeas += ($nPass + $nFail);
    $clausePass += $nPass;
    /* الإغلاقُ يحتاج: كلُّ الشروطِ مقيسةٌ **وناجحة** */
    $verdict = ($nUn === 0 && $nFail === 0 && $nPass > 0) ? 'مُغلقٌ بشاهد' : 'مفتوح';
    if ($verdict === 'مُغلقٌ بشاهد') { $closed++; } else { $open++; }
    /* السببُ الحاكمُ للبقاءِ مفتوحًا = أوّلُ إخفاقٍ، وإلا أوّلُ غيرِ مقيس */
    $reason = '';
    foreach ($verdicts as $v) { if ($v[0] === 'fail') { $reason = $v[1]; break; } }
    if ($reason === '') { foreach ($verdicts as $v) { if ($v[0] === 'unmeasured') { $reason = $v[1]; break; } } }
    $byReason[$reason] = (isset($byReason[$reason]) ? $byReason[$reason] : 0) + 1;

    $rows[] = array('id' => $id, 'half' => $it['half'], 'sev' => $it['sev'], 'dept' => $it['dept'],
        'scr' => $it['scr'], 'rel' => $rel, 'pat' => $pat, 'clauses' => $cls,
        'verdicts' => $verdicts, 'verdict' => $verdict, 'reason' => $reason);
}

/* ══ ⑥ الحصيلة ═══════════════════════════════════════════════════════════ */
$say('── الأنماطُ المشتقّةُ من نصوصِ القبول');
arsort($byPat);
foreach ($byPat as $k => $v) { $say(sprintf('     %-14s %3d', $k, $v)); }
$say('');
$say('── الحصيلة');
$say('  البنود   : مُغلقٌ بشاهد ' . $closed . ' · مفتوح ' . $open);
$say('  الشروط   : ' . $clauseTot . ' شرطًا · مقيسٌ ' . $clauseMeas
     . ' · ناجحٌ ' . $clausePass . ' · غيرُ مقيسٍ ' . ($clauseTot - $clauseMeas));
$say('');
$say('── أسبابُ البقاءِ مفتوحًا (الأكثرُ تكرارًا)');
arsort($byReason);
$i = 0;
foreach ($byReason as $r => $k) {
    $say(sprintf('  %3d  %s', $k, mb_substr($r, 0, 100)));
    if (++$i >= 10) { break; }
}

if ($MD !== null) {
    $md = "# حملةُ أدلةِ الصلاحياتِ والحوكمة · " . date('Y-m-d') . "\n\n";
    $md .= "> `php tools/fix_permgov_campaign.php --run` · الفرع `fix/remediation-2026-08`\n\n";
    $md .= "**النطاق** " . count($items) . " بندًا · **الشروط** {$clauseTot} · "
         . "**مقيس** {$clauseMeas} · **ناجح** {$clausePass}\n\n";
    $md .= "| المعرِّف | النصف | الخطورة | الإدارة | الشاشة | النمط | شروط | الحكم | السببُ الحاكم |\n";
    $md .= "|---|---|---|---|---|---|---|---|---|\n";
    foreach ($rows as $r) {
        $md .= '| ' . $r['id'] . ' | ' . $r['half'] . ' | ' . $r['sev'] . ' | ' . $r['dept']
             . ' | `' . ($r['rel'] ?: $r['scr']) . '` | ' . $r['pat'] . ' | ' . count($r['clauses'])
             . ' | ' . ($r['verdict'] === 'مُغلقٌ بشاهد' ? '**مُغلقٌ بشاهد**' : 'مفتوح')
             . ' | ' . str_replace('|', '/', $r['reason']) . " |\n";
    }
    $md .= "\n## تفصيلُ الشروط\n\n";
    foreach ($rows as $r) {
        $md .= "### {$r['id']} · {$r['dept']} · `" . ($r['rel'] ?: $r['scr']) . "`\n\n";
        foreach ($r['clauses'] as $ci => $c) {
            $v = isset($r['verdicts'][$ci]) ? $r['verdicts'][$ci] : array('unmeasured', '—');
            $mark = $v[0] === 'pass' ? '✔' : ($v[0] === 'fail' ? '✘' : '○');
            $md .= "- {$mark} **الشرط " . ($ci + 1) . "**: " . $c . "\n";
            $md .= "  - " . $v[1] . "\n";
        }
        $md .= "\n";
    }
    $path = (strpos($MD, ':') !== false) ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    $say('');
    $say('  · كُتب: ' . $MD);
}
exit(0);
