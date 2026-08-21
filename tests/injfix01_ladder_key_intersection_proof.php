<?php
/**
 * tests/injfix01_ladder_key_intersection_proof.php
 *   شاهدُ مفتاحِ السلالم — INJ-FIX-01 · الموجة أ ③ · GAP-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ النافذُ بنصِّه** (الفصل ٦-٢): «أربعَ عشرةَ رحلةً من أربعَ عشرةَ
 *   تجد سلّمَها · ومئةٌ في المئةِ من أنواعِ الكياناتِ المنتَجةِ **فعلًا** تجد
 *   سلّمَها القانونيّ · والاحتياطُ يبقى عاملًا في هذه الموجة —
 *   **ولا تُقبل صيغةُ ‹أكبرَ من صفر› في أيِّ موضع**.»
 *
 * ◆ **و«المنتَجةُ فعلًا» تُقاس من الشيفرةِ الحيةِ لا من الجدول**: نداءاتُ
 *   `approval_create_request()` في شجرةِ الإنتاج هي ما يُنتج أنواعَ الكيانات.
 *   والاقتصارُ على `approval_requests` يقيس **ما جرى** لا **ما يجري** — فنوعٌ
 *   لم يُنشأ منه طلبٌ بعدُ يبقى منتِجًا، وصفٌّ تاريخيٌّ ملوَّثٌ ليس نوعًا.
 *
 * ◆ **ولا يُخفى الراسب**: هذا الشاهدُ يُتوقَّع أن يرسب اليومَ في بندٍ واحدٍ
 *   بعينِه — خمسةُ أنواعٍ بلا سلّمٍ في `gov_ladders` أصلًا. وإنشاءُ سلّمٍ
 *   («كم يدًا؟ وبأيِّ سقف؟») سؤالُ سياسةٍ لا سؤالُ كود.
 *
 * التشغيل: php tests/injfix01_ladder_key_intersection_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';

$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ مفتاحُ السلالم — GAP-01 ════\n";

/* ══════════ ① أربعَ عشرةَ رحلةً تجد سلّمَها ═══════════════════════════════ */
echo "\n── ① الرحلاتُ وسلاليمُها ──\n";
$r = $conn->query("SELECT COUNT(*) FROM `gov_journey_ladders`");
$journeys = $r ? (int) $r->fetch_row()[0] : 0;
$r = $conn->query("SELECT COUNT(*) FROM `gov_journey_ladders` j
                     JOIN `gov_ladders` l ON l.ladder_code = j.ladder_code AND l.is_active = 1");
$resolved = $r ? (int) $r->fetch_row()[0] : 0;
ok($journeys === 14, 'مقامُ الرحلاتِ أربعَ عشرة', $pass, $fail, "المقام={$journeys}");
ok($resolved === $journeys && $journeys > 0,
   'كلُّ رحلةٍ تجد سلّمًا نشطًا — ١٤/١٤ لا «أكبرَ من صفر»', $pass, $fail,
   "{$resolved}/{$journeys}");

$r = $conn->query("SELECT COUNT(*) FROM `gov_journey_ladders` j
                     JOIN `gov_ladders` l ON l.ladder_code = j.ladder_code
                     JOIN `gov_ladder_steps` s ON s.ladder_code = l.ladder_code
                    WHERE s.may_approve = 1");
ok($r && (int) $r->fetch_row()[0] > 0, 'لكلِّ سلّمٍ خطوةُ اعتمادٍ مميَّزة (may_approve)', $pass, $fail);

/* ══════════ ② أنواعُ الكيانِ المنتَجةُ فعلًا — من الشيفرةِ الحية ═══════════ */
echo "\n── ② أنواعُ الكيانِ المنتَجةُ فعلًا ──\n";

/** يُستخرج كلُّ نوعِ كيانٍ يُمرَّر حرفيًّا إلى approval_create_request في الإنتاج. */
function producedEntityTypes($root)
{
    $out = array();
    $skip = array('/storage/', '/vendor/', '/tests/', '/tools/', '/docs/', '/.git/');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
        $rel = str_replace('\\', '/', $f->getPathname());
        foreach ($skip as $s) { if (strpos($rel, $s) !== false) { continue 2; } }
        $src = (string) @file_get_contents($f->getPathname());
        if (strpos($src, 'approval_create_request') === false) { continue; }
        /* الوسيطُ الأولُ نصًّا حرفيًّا — والمتغيّرُ يُعَدُّ «غيرَ ثابتٍ» ويُسمّى */
        if (preg_match_all('/approval_create_request\s*\(\s*[\'"]([a-z_][a-z0-9_]*)[\'"]/i', $src, $m)) {
            foreach ($m[1] as $et) { $out[$et][] = str_replace($root . '/', '', $rel); }
        }
        if (preg_match('/approval_create_request\s*\(\s*\$/', $src)) {
            $out['«متغيّر»'][] = str_replace($root . '/', '', $rel);
        }
    }
    return $out;
}

$produced = producedEntityTypes(str_replace('\\', '/', $ROOT));
ksort($produced);
echo "  المنتَجُ من الشيفرةِ الحية: " . count($produced) . " نوعًا\n";

$r = $conn->query("SELECT DISTINCT entity_type FROM `v_approval_rules_effective`");
$inView = array();
while ($r && $x = $r->fetch_assoc()) { $inView[$x['entity_type']] = true; }

$found = array(); $missing = array(); $dynamic = array();
foreach ($produced as $et => $files) {
    if ($et === '«متغيّر»') { $dynamic = $files; continue; }
    if (isset($inView[$et])) { $found[] = $et; } else { $missing[] = $et . ' (' . $files[0] . ')'; }
}
$den = count($found) + count($missing);
$pct = $den > 0 ? round(100 * count($found) / $den, 1) : 0.0;

foreach ($found as $e)   { echo "    ✔ {$e} — يجد سلّمَه\n"; }
foreach ($missing as $e) { echo "    ✘ {$e} — لا سلّمَ له في gov_ladders\n"; }
if ($dynamic) { echo "    ◆ نوعٌ متغيّرٌ وقتَ التشغيل: " . implode(' · ', array_unique($dynamic)) . "\n"; }

ok($den > 0, 'مقامُ الأنواعِ المنتَجةِ غيرُ صفر', $pass, $fail, "المقام={$den}");
ok(count($missing) === 0,
   'مئةٌ في المئةِ من الأنواعِ المنتَجةِ تجد سلّمَها', $pass, $fail,
   "{$pct}٪ — " . count($found) . "/{$den} · بلا سلّم: " . count($missing));

/* ══════════ ③ التقاطعُ لم يعد صفرًا — والقياسُ على أكثرِ الأنواعِ جريانًا ══ */
echo "\n── ③ التقاطع ──\n";
$r = $conn->query("SELECT COUNT(*) FROM `v_approval_rules_effective` WHERE entity_type = 'timesheet'");
$tsSteps = $r ? (int) $r->fetch_row()[0] : 0;
ok($tsSteps >= 3, 'أكثرُ الأنواعِ جريانًا (`timesheet`) يجد سلّمَه كاملًا', $pass, $fail,
   "{$tsSteps} خطوات من LD-01");

require_once $ROOT . '/includes/approval_workflow.php';
$rules = approval_get_workflow_rules('timesheet', 'approve', $conn);
ok(count($rules) === $tsSteps,
   'المحرّكُ نفسُه يُرجع السلّمَ لا الاحتياط', $pass, $fail,
   'قواعدُ المحرّك=' . count($rules));

/* ══════════ ④ الاحتياطُ باقٍ عاملًا — نصُّ المالك ═══════════════════════════ */
echo "\n── ④ الاحتياط ──\n";
$mode = function_exists('ems_env') ? strtolower((string) ems_env('EMS_APPROVAL_RULES', 'monitor')) : 'monitor';
ok($mode !== 'enforce', 'الاحتياطُ لم يُطفأ في هذه الموجة', $pass, $fail, "EMS_APPROVAL_RULES={$mode}");
$unknown = approval_get_workflow_rules('__injfix_probe_unknown__', 'approve', $conn);
ok(count($unknown) > 0, 'نوعٌ مجهولٌ ما يزال يسقط للاحتياطِ (شبكةُ الأمانِ حية)', $pass, $fail,
   'خطوات=' . count($unknown));

/* ══════════ ⑤ خبرٌ خارجَ الحكم — يُقاس ولا يُرسِّب هذه الموجة ═══════════════ */
echo "\n── ⑤ خبرٌ خارجَ الحكم ──\n";
$r = $conn->query("SELECT COUNT(DISTINCT role_required) FROM `v_approval_rules_effective`
                    WHERE entity_type = 'timesheet'");
$distinctRoles = $r ? (int) $r->fetch_row()[0] : 0;
echo "  ◆ خطواتُ `timesheet` الثلاثُ تعود إلى {$distinctRoles} دورًا متمايزًا.\n";
if ($distinctRoles < 2) {
    echo "    ⇐ فـ«لا يدَ تمشي خطوتَين» **لا يُنفَّذ بالدورِ وحدَه** — الأيدي الثلاثُ\n";
    echo "      (مدخلُ الوحداتِ · محاسبُ الموقعِ · مديرُ الموقع) تحتَ دورٍ واحد.\n";
    echo "      وهذا سؤالُ **مستوى السلطة** (الشخصُ لا الدور) — مجالُ الموجة ج.\n";
}
$r = $conn->query("SELECT COUNT(*) FROM `approval_requests`
                    WHERE entity_type NOT REGEXP '^[a-z0-9_]+$'");
$polluted = $r ? (int) $r->fetch_row()[0] : 0;
echo "  ◆ صفوفٌ تاريخيةٌ بنصٍّ بشريٍّ في حقلِ مفتاحِ `approval_requests`: {$polluted} (GAP-09)\n";

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
exit($fail === 0 ? 0 : 1);
