<?php
/**
 * tools/fix_wf_rules_probe.php — قياسُ سلاليمِ الاعتمادِ في النظامِ كلِّه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الدَّينُ المُعلَنُ في معمارية v21 §11-②: `approval_workflow_rules` مملوءٌ
 *   بنصوصِ ملاحظاتِ UAT في خاناتِ `entity_type`/`action`/`role_required`، فلا
 *   قاعدةَ حقيقيةً تُطابِق أيَّ نوعِ كيانٍ حيّ — وكلُّ سلّمٍ يسقط على احتياطٍ
 *   مكتوبٍ في الشيفرة: **خطوةٌ واحدةٌ للسوبر**. ومنشئُ الطلبِ يُعتمَد له
 *   تلقائيًّا إن طابق دورَ الخطوة ⇒ **اعتمادُ ذاتٍ بيدٍ واحدةٍ في خطوةٍ واحدة**.
 *
 * ◆ ولا يُبنى إصلاحٌ على وصف. يُقاس هنا:
 *   ① القواعدُ: كم منها حقيقيٌّ وكم نصُّ ملاحظة؟
 *   ② الأنواعُ الحيةُ: ما يجري فعلًا في `approval_requests` — بمن طلب ومن اعتمد.
 *   ③ **الشاهدُ الدامغ**: كم طلبًا اعتمده **طالبُه نفسُه**، وكم سلّمًا مشته يدٌ واحدة؟
 *   ④ الاحتياطُ في الشيفرة: أيُّ أزواجٍ مذكورةٌ فيه وأيُّها بلا ذكر؟
 *
 * التشغيل: php tools/fix_wf_rules_probe.php [--md=مسار]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
require_once dirname(__DIR__) . '/includes/env.php';   // وإلا طُبع وضعُ العلمِ «؟»
$db = fix_db();
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }
$L = array();
function o($s) { global $L; $L[] = $s; echo $s . "\n"; }

/** عددٌ من استعلامٍ — والفشلُ يعود null لا صفرًا (گوتشا مسجَّلة). */
function q($db, $sql)
{
    $r = $db->query($sql);
    if (!$r) { return null; }
    $row = $r->fetch_row();
    return $row ? (int) $row[0] : 0;
}

/** أهذا نصُّ ملاحظةٍ أم رمزُ كيان؟ الرمزُ: بلا فراغٍ وقصيرٌ ولاتينيّ. */
function is_code($s)
{
    $s = trim((string) $s);
    return $s !== '' && mb_strlen($s) <= 40 && strpos($s, ' ') === false
        && preg_match('/^[A-Za-z0-9_:\-]+$/', $s) === 1;
}

o('══════════════════════════════════════════════════════════════════════');
o(' سلاليمُ الاعتماد — قياسٌ حيّ · ' . date('Y-m-d H:i'));
o('══════════════════════════════════════════════════════════════════════');

/* ══ ① القواعدُ المسجَّلة ═══════════════════════════════════════════════ */
o("\n── ① جدولُ القواعد");
/* ◆ الحاكمُ هو **النشط** وحدَه: `approval_get_workflow_rules` تشترط
     `is_active = 1`. فعدُّ المعطَّلِ حاكمًا يُنتج رقمًا لا يحكم شيئًا — وهو
     مقامٌ خطأٌ لا قيمةٌ خطأ. والمعطَّلُ يُعلَن في سطرٍ مستقلٍّ لأنه **باقٍ**
     (لا حذفَ في هذه المعمارية) فوجودُه ليس عطلًا. */
$rows = array(); $offRows = array();
$rs = $db->query('SELECT id, entity_type, action, role_required, step_order, is_active FROM approval_workflow_rules ORDER BY id');
while ($rs && ($x = $rs->fetch_assoc())) {
    if ((int) $x['is_active'] === 1) { $rows[] = $x; } else { $offRows[] = $x; }
}
$real = array(); $junk = 0;
foreach ($rows as $r) {
    if (is_code($r['entity_type']) && is_code($r['action'])) {
        $k = $r['entity_type'] . ':' . $r['action'];
        if (!isset($real[$k])) { $real[$k] = array(); }
        $real[$k][] = $r;
    } else { $junk++; }
}
$junkOff = 0;
foreach ($offRows as $r) { if (!is_code($r['entity_type']) || !is_code($r['action'])) { $junkOff++; } }
o('  صفوفٌ **نشطة**: ' . count($rows) . ' · منها قواعدُ برمزٍ حقيقيّ: **' . (count($rows) - $junk)
  . '** · بنصِّ ملاحظةٍ في خانةِ الرمز: **' . $junk . '**');
o('  ومعطَّلٌ باقٍ (مُعلَنٌ لا محذوف): ' . count($offRows) . ' · منه نصوصُ ملاحظاتٍ: ' . $junkOff);
o('  أزواجٌ (كيان:فعل) لها قواعدُ حاكمة: ' . count($real));
foreach ($real as $k => $steps) {
    usort($steps, static function ($a, $b) { return (int) $a['step_order'] - (int) $b['step_order']; });
    $d = array();
    foreach ($steps as $s) { $d[] = $s['step_order'] . '→' . $s['role_required']; }
    o('    · ' . str_pad($k, 32) . count($steps) . ' خطوة: ' . implode(' · ', $d));
}
if ($junk > 0) {
    o('  ✘ عيّنةٌ من نصوصِ الملاحظاتِ **النشطة** (خانةُ `entity_type`):');
    $n = 0;
    foreach ($rows as $r) {
        if (!is_code($r['entity_type']) && ++$n <= 3) { o('      «' . mb_substr($r['entity_type'], 0, 58) . '…»'); }
    }
} elseif ($junkOff > 0) {
    o('  ✔ ولا نصَّ ملاحظةٍ نشطًا — والعشرون معطَّلةٌ ببصمةٍ تُعلن السبب:');
    o('      «' . mb_substr((string) $offRows[0]['action'], 0, 74) . '»');
}

/* ══ ② ما يجري فعلًا ═══════════════════════════════════════════════════ */
o("\n── ② الأنواعُ الحيةُ في `approval_requests`");
$live = array();
$rs = $db->query("SELECT entity_type, action, COUNT(*) n,
                         SUM(status='approved') ap, SUM(status='pending') pn, SUM(status='rejected') rj
                    FROM approval_requests GROUP BY entity_type, action ORDER BY n DESC");
/* ◆ يُفصل نوعان لا يُخلطان: **نوعٌ حقيقيٌّ بلا قواعد** = دَينُ سياسةٍ يُسدّ
     بتسجيلِ قواعد؛ **ونوعٌ محتواه نصُّ ملاحظة** = سجلُّ واقعةٍ ملوَّثٌ لا سلّمٌ
     يُعرَّف — ولا يُطلب له قواعدُ أبدًا. */
$gapPairs = array(); $dirtyReqs = 0;
while ($rs && ($x = $rs->fetch_assoc())) {
    $live[] = $x;
    $k = $x['entity_type'] . ':' . $x['action'];
    $codeOk = is_code($x['entity_type']) && is_code($x['action']);
    if (!$codeOk) { $dirtyReqs += (int) $x['n']; continue; }
    $verdict = isset($real[$k]) ? '✔ له قواعدُ حاكمة (' . count($real[$k]) . ' خطوة)'
                                : '✘ **بلا قواعد ⇒ احتياطُ خطوةٍ واحدة**';
    if (!isset($real[$k])) { $gapPairs[$k] = (int) $x['n']; }
    o('  ' . str_pad($k, 30) . 'طلبات=' . str_pad($x['n'], 4) . ' معتمد=' . str_pad($x['ap'], 4)
      . ' معلق=' . str_pad($x['pn'], 4) . ' مرفوض=' . str_pad($x['rj'], 4) . $verdict);
}
if (!$live) { o('  (لا طلبات)'); }
if ($dirtyReqs > 0) {
    o('  ◆ وطلباتٌ نوعُها **نصُّ ملاحظةٍ** لا رمزُ كيان: ' . $dirtyReqs
      . ' — سجلاتُ وقائعَ ملوَّثةٌ (لها تواريخُ وحمولات)، **تُعلَن ولا تُكتب من جديد**؛ فتعديلُ سجلِّ واقعةٍ تلفيقٌ لا تنظيف.');
}
if ($gapPairs) {
    o('  ◆ أزواجٌ **حقيقيةٌ** تنتظر قرارَ سياسةٍ («كم يدًا؟»): ' . count($gapPairs));
    foreach ($gapPairs as $k => $n) { o('      · ' . str_pad($k, 30) . $n . ' طلبًا'); }
}

/* ══ ③ الشاهدُ الدامغ: أيدي السلّم ═════════════════════════════════════ */
o("\n── ③ كم يدًا مشت كلَّ سلّمٍ مكتمل؟");
$rs = $db->query("SELECT ar.id, ar.entity_type, ar.action, ar.requested_by, ar.status,
                         COUNT(s.id) steps, COUNT(DISTINCT s.approved_by) hands,
                         SUM(s.approved_by = ar.requested_by) by_requester
                    FROM approval_requests ar
                    LEFT JOIN approval_steps s ON s.request_id = ar.id AND s.status='approved'
                   GROUP BY ar.id ORDER BY ar.id");
$oneHand = 0; $selfApproved = 0; $multi = 0; $tot = 0; $details = array();
while ($rs && ($x = $rs->fetch_assoc())) {
    $tot++;
    $h = (int) $x['hands'];
    if ((int) $x['by_requester'] > 0) { $selfApproved++; }
    if ($x['status'] === 'approved') {
        if ($h <= 1) { $oneHand++; } else { $multi++; }
        $details[] = $x;
    }
}
o('  طلباتٌ إجمالًا: ' . $tot);
o('  منها **مكتملٌ بيدٍ واحدةٍ**: ' . $oneHand . ' · بيدين أو أكثر: ' . $multi);
o('  وطلباتٌ **اعتمدها طالبُها نفسُه** (ولو خطوةً): **' . $selfApproved . '**');
foreach (array_slice($details, 0, 8) as $x) {
    o('    #' . str_pad($x['id'], 4) . str_pad($x['entity_type'] . ':' . $x['action'], 30)
      . 'خطوات=' . $x['steps'] . ' أيدٍ=' . $x['hands']
      . ' طالبُه=' . $x['requested_by'] . ($x['by_requester'] > 0 ? '  ◄ اعتمده طالبُه' : ''));
}

/* ══ ④ الاحتياطُ في الشيفرة ════════════════════════════════════════════ */
o("\n── ④ الاحتياطُ المكتوبُ في `includes/approval_workflow.php`");
$src = (string) file_get_contents($ROOT . '/includes/approval_workflow.php');
$fb = array();
if (preg_match('/\$fallback_map\s*=\s*\[(.*?)\]\s*;/s', $src, $m)) {
    if (preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", $m[1], $mm, PREG_SET_ORDER)) {
        foreach ($mm as $p) { $fb[$p[1]] = $p[2]; }
    }
}
o('  أزواجٌ في الاحتياطِ المسمّى: ' . count($fb));
foreach ($fb as $k => $v) {
    $steps = count(explode(',', $v));
    o('    · ' . str_pad($k, 34) . 'أدوار=' . str_pad($v, 8) . ' → **خطوةٌ واحدةٌ** بأيٍّ من ' . $steps . ' دور');
}
$defaultOne = strpos($src, "'role_required' => EMS_ROLE_SUPER_ADMIN, 'step_order' => 1") !== false;
o('  والاحتياطُ الأخيرُ (لا قاعدةَ ولا زوجَ مسمّى): '
  . ($defaultOne ? '**خطوةٌ واحدةٌ للسوبر** — والسوبرُ يطابق أيَّ خطوةٍ بحكمِ المحرّك' : 'غيرُ معروف'));
$autoFirst = strpos($src, 'اعتماد تلقائي (منشئ الطلب يملك صلاحية المرحلة)') !== false;
o('  واعتمادُ الخطوةِ الأولى تلقائيًّا لمنشئِ الطلب: ' . ($autoFirst ? '**قائم**' : 'غير قائم'));
$hands = strpos($src, 'لا تمشي خطوتين') !== false;
o('  وقاعدةُ «لا يدَ تمشي خطوتين»: ' . ($hands ? '✔ قائمة' : '✘ غائبة'));

/* ══ الحكم ═════════════════════════════════════════════════════════════ */
/* ══ ⑤ الثقبُ البنيويّ: صفرُ خطوةٍ = «مكتمل»؟ ═══════════════════════════ */
o("\n── ⑤ الثقبُ البنيويُّ (صدقٌ خلوًّا)");
$src2 = (string) file_get_contents($ROOT . '/includes/approval_workflow.php');
$closed = strpos($src2, 'صفرُ خطوةٍ ليس اكتمالًا') !== false
       && preg_match('/if\s*\(\s*\$total\s*===\s*0\s*\)\s*\{\s*return false/', $src2);
o('  «أكلُّ الخطواتِ معتمدة؟» ترفض صفرَ خطوة: ' . ($closed ? '✔ نعم' : '✘ **لا — طلبٌ بلا توقيعٍ يُقرأ معتمدًا**'));
$sl = q($db, "SELECT COUNT(*) FROM approval_requests ar
               WHERE NOT EXISTS (SELECT 1 FROM approval_steps s WHERE s.request_id = ar.id)");
$slAp = q($db, "SELECT COUNT(*) FROM approval_requests ar
                 WHERE ar.status='approved' AND NOT EXISTS (SELECT 1 FROM approval_steps s WHERE s.request_id = ar.id)");
o('  وطلباتٌ بلا أيِّ خطوةٍ في القاعدة: ' . $sl . ' — منها موسومٌ `approved`: ' . $slAp
  . ($slAp > 0 ? ' ◄ **سابقةٌ للإصلاح** (حمولتُها نُفِّذت) — تُعلَن ولا تُكتب من جديد' : ''));
$gapLog = q($db, "SELECT COUNT(*) FROM guard_denials WHERE guard_code='approval_rules_missing'");
o('  وسطورُ دَينِ «سلّمٌ بلا قاعدة» المسجَّلة: ' . $gapLog . ' (وضعُ العلم: '
  . (function_exists('ems_env') ? (string) ems_env('EMS_APPROVAL_RULES', 'monitor') : '؟') . ')');

o("\n" . str_repeat('═', 70));
$verdict = (count($real) > 0 && $junk === 0 && $closed);
o('الحكم: ' . ($verdict
    ? '✔ صفرُ قاعدةٍ حاكمةٍ بنصِّ ملاحظةٍ · والثقبُ الخلويُّ مغلق'
    : '✘ ' . ($junk > 0 ? $junk . ' قاعدةً حاكمةً بنصِّ ملاحظةٍ · ' : '') . (!$closed ? 'والثقبُ الخلويُّ مفتوح' : '')));
if ($gapPairs) {
    o('  ◆ ودَينٌ **معلَنٌ بأرقامِه لا مُغلَقٌ**: ' . count($gapPairs) . ' زوجًا حقيقيًّا بلا قواعد ⇒ سلّمُه');
    o('    **خطوةٌ واحدة**، ومنشئُ الطلبِ قد يعتمدها. والعلمُ `EMS_APPROVAL_RULES`');
    o('    يسجّل كلَّ استعمالٍ للاحتياطِ في `guard_denials`؛ وقلبُه إلى `enforce`');
    o('    **قرارُ مالكٍ** يسبقه تسجيلُ قواعدَ لهذه الأزواج — «كم يدًا لكلٍّ؟».');
}
o(str_repeat('═', 70));

if ($mdOut) {
    $md = "# سلاليمُ الاعتماد — قياسٌ حيّ · " . date('Y-m-d H:i') . "\n\n```\n" . implode("\n", $L) . "\n```\n";
    file_put_contents($mdOut, $md);
    echo "كُتب: {$mdOut}\n";
}
exit($verdict ? 0 : 1);
