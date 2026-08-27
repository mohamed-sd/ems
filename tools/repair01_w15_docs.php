<?php
/**
 * tools/repair01_w15_docs.php — إسقاطُ مخرَجاتِ المرحلةِ الخامسةَ عشرة على القرص
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المخزنُ هو المصدرُ والوثيقةُ إسقاط** — وأيُّ فرقٍ بينهما يُصلَح في المخزنِ
 *   ثمّ يُعاد التوليد. ⛔ **ولا تُحرَّر وثيقةٌ مولَّدةٌ يدويًّا.**
 *
 * التشغيل: php tools/repair01_w15_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DIR = $ROOT . '/docs/REPAIR01_20260823/plan';
$rows = function ($sql) use ($conn) {
    $out = array(); $r = $conn->query($sql);
    while ($r && ($x = $r->fetch_assoc())) { $out[] = $x; }
    return $out;
};
$md = function ($s) { return str_replace(array('|', "\n"), array('\\|', ' '), (string) $s); };

/* ═══ ① آلاتُ الحالة ═══════════════════════════════════════════════════ */
$L = array();
$L[] = '# RPR-W15 — آلاتُ الحالةِ لكيانات المساحاتِ والتقارير';
$L[] = '';
$L[] = '> ⛔ **مولَّدٌ من المخزن** — `php tools/repair01_w15_docs.php`. لا يُحرَّر يدويًّا.';
$L[] = '> المصدر: `repair01_w15_states`. ولكلِّ انتقالٍ مالكُه وشرطُه ومستندُه وبوّابةُ اعتمادِه';
$L[] = '> وقاعدةُ إعادةِ فتحِه وقاعدةُ تصحيحِه — **ولكلِّ ممنوعٍ سببُه صراحةً**.';
$L[] = '';
$st = $rows("SELECT * FROM repair01_w15_states ORDER BY entity, id");
$byEnt = array();
foreach ($st as $s) { $byEnt[$s['entity']][] = $s; }
$L[] = sprintf('**كياناتٌ %d · انتقالاتٌ %d · منها ممنوعٌ صراحةً %d**',
    count($byEnt), count($st), count(array_filter($st, function ($x) { return (int) $x['allowed'] === 0; })));
$L[] = '';
foreach ($byEnt as $ent => $list) {
    $L[] = '## `' . $ent . '`';
    $L[] = '';
    $L[] = '| من | إلى | مالك الانتقال | الشرط المسبق | المستند | بوابة الاعتماد | إعادة الفتح | التصحيح |';
    $L[] = '|---|---|---|---|---|---|---|---|';
    foreach ($list as $s) {
        if ((int) $s['allowed'] === 0) { continue; }
        $L[] = '| ' . $md($s['from_state']) . ' | ' . $md($s['to_state']) . ' | ' . $md($s['owner_role'])
             . ' | ' . $md($s['precondition']) . ' | ' . $md($s['official_doc'])
             . ' | ' . $md($s['approval_gate']) . ' | ' . $md($s['reopen_rule']) . ' | ' . $md($s['correct_rule']) . ' |';
    }
    $L[] = '';
    $forb = array_filter($list, function ($x) { return (int) $x['allowed'] === 0; });
    if ($forb) {
        $L[] = '**⛔ ممنوعٌ صراحةً:**';
        $L[] = '';
        $L[] = '| من | إلى | السبب |';
        $L[] = '|---|---|---|';
        foreach ($forb as $s) {
            $L[] = '| ' . $md($s['from_state']) . ' | ' . $md($s['to_state']) . ' | ' . $md($s['forbid_why']) . ' |';
        }
        $L[] = '';
    }
}
file_put_contents($DIR . '/W15_STATE_MACHINES.md', implode("\n", $L) . "\n");
echo "  ✔ W15_STATE_MACHINES.md (" . count($byEnt) . " كيانًا · " . count($st) . " انتقالًا)\n";

/* ═══ ② مصفوفةُ فصلِ الواجبات ═════════════════════════════════════════ */
$L = array();
$L[] = '# RPR-W15 — مصفوفةُ فصلِ الواجباتِ للمساحاتِ والتقارير';
$L[] = '';
$L[] = '> ⛔ **مولَّدٌ من المخزن** — المصدر: `repair01_w15_sod`.';
$L[] = '> ⛔ **ولا اسمَ شخصٍ صلبًا** — `Role_Key` و`Authority_Rule_ID` و`Deputy_Role` و`Scope`';
$L[] = '> و`Delegation` و`Effective_Date`.';
$L[] = '';
$sod = $rows("SELECT * FROM repair01_w15_sod ORDER BY id");
$L[] = sprintf('**عملياتٌ حرجةٌ %d — كلٌّ بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ صراحةً**', count($sod));
$L[] = '';
foreach ($sod as $s) {
    $L[] = '## ' . $s['process_ar'] . '  ·  `' . $s['process_key'] . '`';
    $L[] = '';
    $L[] = '| الدور | الجهة |';
    $L[] = '|---|---|';
    $L[] = '| المُطلِق | ' . $md($s['initiator']) . ' |';
    $L[] = '| المراجع | ' . $md($s['reviewer']) . ' |';
    $L[] = '| المعتمِد | ' . $md($s['approver']) . ' |';
    $L[] = '| المنفِّذ | ' . $md($s['executor']) . ' |';
    $L[] = '| المُغلِق | ' . $md($s['closer']) . ' |';
    $L[] = '';
    $L[] = '⛔ **التركيبةُ الممنوعة:** ' . $s['forbidden_combo'];
    $L[] = '';
    $L[] = '`Authority_Rule_ID` ' . $s['authority_rule'] . ' · `Deputy_Role` ' . $s['deputy_role']
         . ' · `Scope` ' . $s['scope_rule'] . ' · `Delegation` ' . $s['delegation']
         . ' · `Effective_Date` ' . $s['effective_date'];
    $L[] = '';
}
file_put_contents($DIR . '/W15_SOD.md', implode("\n", $L) . "\n");
echo "  ✔ W15_SOD.md (" . count($sod) . " عمليّةً)\n";

/* ═══ ③ عقودُ الأثر ═══════════════════════════════════════════════════ */
$L = array();
$L[] = '# RPR-W15 — عقودُ الأثرِ للأحداثِ الصادرةِ من المساحات';
$L[] = '';
$L[] = '> ⛔ **مولَّدٌ من المخزن** — المصدر: `repair01_events` حيث `wave = \'W15\'`.';
$L[] = '> ⛔ **وحدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** — والقبولُ يقيس **الأثرَ التجاريَّ**';
$L[] = '> لا صفَّ الحدثِ المُنشَأ، **وكلُّ مستهلكٍ بالاسم** لا «كلُّ المستهلكين».';
$L[] = '';
$ev = $rows("SELECT * FROM repair01_events WHERE wave = 'W15' ORDER BY id");
$L[] = sprintf('**عقودٌ مسجَّلةٌ %d**', count($ev));
$L[] = '';
foreach ($ev as $e) {
    $L[] = '## `' . $e['event_code'] . '` — ' . $e['name'];
    $L[] = '';
    $L[] = '| البند | القيمة |';
    $L[] = '|---|---|';
    $L[] = '| المصدر | ' . $md($e['source_unit']) . ' · `' . $md($e['source_screen']) . '` |';
    $L[] = '| المحفِّز | ' . $md($e['trigger_rule']) . ' |';
    $L[] = '| الحمولةُ الدنيا | ' . $md($e['min_payload']) . ' |';
    $L[] = '| مفتاحُ منعِ التكرار | `' . $md($e['idempotency_key']) . '` |';
    $L[] = '| الشروطُ المسبقة | ' . $md($e['preconditions']) . ' |';
    $L[] = '| سياسةُ الإعادة | ' . $md($e['retry_policy']) . ' |';
    $L[] = '| سلوكُ الفشل | ' . $md($e['failure_policy']) . ' |';
    $L[] = '| التعويض | ' . $md($e['compensation']) . ' |';
    $L[] = '';
    $L[] = '**المستهلكونَ بالاسمِ وأثرُ كلٍّ منهم:**';
    $L[] = '';
    $cons = array_map('trim', explode('·', (string) $e['consumer_list']));
    $effs = array_map('trim', explode('|', (string) $e['consumer_effect']));
    $L[] = '| المستهلك | الأثرُ المقيس |';
    $L[] = '|---|---|';
    foreach ($cons as $i => $c) {
        if ($c === '') { continue; }
        $L[] = '| ' . $md($c) . ' | ' . $md(isset($effs[$i]) ? $effs[$i] : '') . ' |';
    }
    $L[] = '';
}
file_put_contents($DIR . '/W15_EVENT_CONTRACTS.md', implode("\n", $L) . "\n");
echo "  ✔ W15_EVENT_CONTRACTS.md (" . count($ev) . " عقدًا)\n";

/* ═══ ④ دليلُ الرحلة ══════════════════════════════════════════════════ */
$L = array();
$L[] = '# RPR-W15 — دليلُ عبورِ رحلةِ الطلب';
$L[] = '';
$L[] = '> ⛔ **مولَّدٌ من المخزن** — المصدر: `repair01_w15_journey`.';
$L[] = '> **موظّفٌ يفتح «طلباتي» ← إجازةٌ تُنشأ في القوى التشغيليّة ← وصيانةٌ عند مالكِها ←';
$L[] = '> وبلاغٌ في إدارةِ البلاغات ← الإسقاطُ يعرض الثلاثةَ ← تعديلُ الحالةِ عند المالكِ';
$L[] = '> ينعكس فيه — ⛔ ولا نسخةَ محلّيّة. ثمّ القيادةُ ترى الأثرَ ولا تملك المعاملة.**';
$L[] = '';
$j = $rows("SELECT * FROM repair01_w15_journey ORDER BY id");
$legs = array();
foreach ($j as $x) { $legs[$x['leg']][] = $x; }
$L[] = sprintf('**محطّاتٌ %d · أشواطٌ %d · عبرت %d · مستهلكونَ متمايزون %d**',
    count($j), count($legs),
    count(array_filter($j, function ($x) { return $x['verdict'] === 'PASS'; })),
    count(array_unique(array_map(function ($x) { return $x['consumer']; }, $j))));
$L[] = '';
foreach ($legs as $leg => $list) {
    $L[] = '## الشوط `' . $leg . '`';
    $L[] = '';
    $L[] = '| # | المحطّة | الفاعل | النداء | المتوقَّع | المقيس | المستهلك | الأثرُ التجاريّ | الحكم |';
    $L[] = '|---:|---|---|---|---|---|---|---|---|';
    foreach ($list as $x) {
        $L[] = '| ' . (int) $x['step_no'] . ' | ' . $md($x['station']) . ' | ' . $md($x['actor_role'])
             . ' | `' . $md($x['service_call']) . '` | ' . $md($x['expect']) . ' | ' . $md($x['measured'])
             . ' | ' . $md($x['consumer']) . ' | ' . $md($x['effect_probe'])
             . ' | ' . ($x['verdict'] === 'PASS' ? '✔' : '✘') . ' |';
    }
    $L[] = '';
}
file_put_contents($DIR . '/W15_JOURNEY_EVIDENCE.md', implode("\n", $L) . "\n");
echo "  ✔ W15_JOURNEY_EVIDENCE.md (" . count($j) . " محطّةً)\n";

echo "\nتمَّ توليدُ المخرَجاتِ من المخزن.\n";
exit(0);
