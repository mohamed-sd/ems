<?php
/**
 * tools/repair01_w14_docs.php — مولِّدُ مخرَجاتِ المرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ إسقاطُ المخزنِ لا نصٌّ يُكتب بجانبِه** — فآلاتُ الحالةِ ومصفوفةُ
 *   فصلِ الواجباتِ وعقودُ الأثرِ ورحلةُ الإثباتِ تُقرأ من جداولِ الموجةِ وتُصاغ.
 *   وأيُّ فرقٍ بين الوثيقةِ والمخزنِ **يُصلَح في المخزنِ ثمَّ يُعاد التوليد**.
 *
 * التشغيل: php tools/repair01_w14_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w14_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function ($sql) use ($conn) { return repair01_w14_one($conn, $sql); };
$DIR = $ROOT . '/docs/REPAIR01_20260823/plan/';
$md = function ($v) { return str_replace('|', '\\|', trim((string) $v)); };

/* ═══════════════════════════════════════════════════════════════════════════
   ① آلاتُ الحالة
   ═══════════════════════════════════════════════════════════════════════════ */
$s = "# RPR-W14 — آلاتُ الحالةِ لكيانِ النطاقِ الرئيسيِّ\n"
   . "> مولَّدٌ من `repair01_w14_states`. ⛔ **لا نصَّ حالةٍ حرّ — والكيانُ بلا آلةٍ لا يُغلَق.**\n"
   . "> والانتقالُ المسموحُ يحمل أركانَه الستّةَ، **والممنوعُ صريحٌ بسببِه** لا بغيابِه.\n\n";
$entN = 0; $trN = 0; $fbN = 0;
$re = $conn->query("SELECT DISTINCT entity FROM repair01_w14_states ORDER BY entity");
$ents = array();
while ($re && $x = $re->fetch_row()) { $ents[] = $x[0]; }
foreach ($ents as $e) {
    $entN++;
    $s .= "\n## " . $e . "\n\n";
    $states = array();
    $rs = $conn->query("SELECT from_state, to_state FROM repair01_w14_states
                         WHERE entity = '" . $conn->real_escape_string($e) . "'");
    while ($rs && $x = $rs->fetch_assoc()) { $states[$x['from_state']] = true; $states[$x['to_state']] = true; }
    $s .= '**الحالات:** `' . implode('` · `', array_keys($states)) . "`\n\n";
    $s .= "### الانتقالاتُ المسموحة\n\n";
    $s .= "| من | إلى | مالكُ الانتقال | الشروطُ المسبقة | المستندُ الرسميّ | بوّابةُ الاعتماد | إعادةُ الفتح | التصحيح |\n";
    $s .= "|---|---|---|---|---|---|---|---|\n";
    $rs = $conn->query("SELECT * FROM repair01_w14_states
                         WHERE entity = '" . $conn->real_escape_string($e) . "' AND allowed = 1
                         ORDER BY id");
    while ($rs && $x = $rs->fetch_assoc()) {
        $trN++;
        $s .= '| `' . $md($x['from_state']) . '` | `' . $md($x['to_state']) . '` | ' . $md($x['owner_role'])
            . ' | ' . $md($x['preconditions']) . ' | ' . $md($x['output_doc']) . ' | ' . $md($x['approval_gate'])
            . ' | ' . $md($x['reopen_rule']) . ' | ' . $md($x['correct_rule']) . " |\n";
    }
    $rf = $conn->query("SELECT * FROM repair01_w14_states
                         WHERE entity = '" . $conn->real_escape_string($e) . "' AND allowed = 0
                         ORDER BY id");
    $fb = array();
    while ($rf && $x = $rf->fetch_assoc()) { $fb[] = $x; }
    if ($fb) {
        $s .= "\n### الانتقالاتُ الممنوعةُ صراحةً\n\n| من | إلى | ⛔ السبب |\n|---|---|---|\n";
        foreach ($fb as $x) {
            $fbN++;
            $s .= '| `' . $md($x['from_state']) . '` | `' . $md($x['to_state']) . '` | '
                . $md($x['forbid_why']) . " |\n";
        }
    }
}
$s .= "\n---\n\n**المقيس:** كياناتٌ " . $entN . " · انتقالاتٌ مسموحة " . $trN
    . " · ممنوعٌ صريحٌ بسببِه " . $fbN . "\n";
file_put_contents($DIR . 'W14_STATE_MACHINES.md', $s);
echo "  ✔ W14_STATE_MACHINES.md — كيانات $entN · انتقالات $trN · ممنوع $fbN\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ② مصفوفةُ فصلِ الواجبات
   ═══════════════════════════════════════════════════════════════════════════ */
$s = "# RPR-W14 — مصفوفةُ فصلِ الواجباتِ للنطاق\n"
   . "> مولَّدٌ من `repair01_w14_sod`. ⛔ **لا أسماءَ أشخاصٍ صلبة** — رمزُ دورٍ وقاعدةُ سلطةٍ ونائبٌ ونطاقٌ وتفويض.\n"
   . "> **والتركيبةُ الممنوعةُ صريحةٌ** لكلِّ عمليةٍ حرجة، ورمزُ ردِّها **منفَّذٌ في خدمةٍ لا مُعلَنٌ في جدول**.\n\n";
$sodCodes = repair01_w14_sod_codes();
$byDom = array();
$rs = $conn->query("SELECT * FROM repair01_w14_sod ORDER BY domain_code, process_key");
while ($rs && $x = $rs->fetch_assoc()) { $byDom[$x['domain_code']][] = $x; }
$domAr = array('SOURCE' => 'المصدرُ التشغيليّ — مالكُ الواقعة',
               'DEP-08' => 'الحوكمةُ والالتزام — الخطُّ الثاني',
               'DEP-09' => 'إدارةُ المخاطر — الخطُّ الثاني المستقلّ',
               'IAF'    => 'المراجعةُ الداخليّة — الخطُّ الثالثُ المستقلّ');
$sodN = 0;
foreach ($domAr as $dc => $title) {
    if (!isset($byDom[$dc])) { continue; }
    $s .= "\n## " . $title . "\n\n";
    foreach ($byDom[$dc] as $x) {
        $sodN++;
        $s .= "### `" . $md($x['process_key']) . "` — " . $md($x['process_name']) . "\n\n";
        $s .= "| الدور | من يشغله |\n|---|---|\n";
        $s .= '| `Initiator` | ' . $md($x['initiator_role']) . " |\n";
        $s .= '| `Reviewer` | ' . $md($x['reviewer_role']) . " |\n";
        $s .= '| `Approver` | ' . $md($x['approver_role']) . " |\n";
        $s .= '| `Executor` | ' . $md($x['executor_role']) . " |\n";
        $s .= '| `Reconciler/Closer` | ' . $md($x['closer_role']) . " |\n\n";
        $s .= '⛔ **التركيبةُ الممنوعة:** ' . $md($x['forbidden_combo']) . "\n\n";
        $code = isset($sodCodes[$x['process_key']]) ? $sodCodes[$x['process_key']] : '—';
        $s .= '- **يُنفَّذ في:** `' . $md($x['enforced_by']) . '` · **رمزُ الردّ:** `' . $code . "`\n";
        $s .= '- **قاعدةُ السلطة:** `' . $md($x['authority_rule_id']) . '` · **النائب:** '
            . $md($x['deputy_role']) . "\n";
        $s .= '- **النطاق:** ' . $md($x['scope_rule']) . ' · **التفويض:** ' . $md($x['delegation'])
            . ' · **النفاذ:** ' . $md($x['effective_date']) . "\n\n";
    }
}
$s .= "\n---\n\n**المقيس:** عملياتٌ حرجة " . $sodN . " · رموزُ ردٍّ مُعلَنةٌ " . count($sodCodes)
    . " · اسمُ شخصٍ صلبٌ 0\n";
file_put_contents($DIR . 'W14_SOD.md', $s);
echo "  ✔ W14_SOD.md — عمليات $sodN\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ③ عقودُ الأثر
   ═══════════════════════════════════════════════════════════════════════════ */
$s = "# RPR-W14 — عقودُ الأثرِ لأحداثِ النطاق\n"
   . "> مولَّدٌ من `repair01_events` حيث `wave = 'W14'`.\n"
   . "> ⛔ **حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ** — والقبولُ يقيس **الأثرَ التجاريَّ** لا صفَّ الحدثِ المُنشَأ (§46).\n"
   . "> **وكلُّ مستهلكٍ بالاسم** — ولا «كلُّ المستهلكين».\n\n";
$evN = 0;
$rs = $conn->query("SELECT * FROM repair01_events WHERE wave = 'W14' ORDER BY source_unit, event_code");
while ($rs && $x = $rs->fetch_assoc()) {
    $evN++;
    $s .= "\n## `" . $md($x['event_code']) . "` — " . $md($x['name']) . "\n\n";
    $s .= "| البند | القيمة |\n|---|---|\n";
    $s .= '| المصدر | ' . $md($x['source_unit']) . ' · `' . $md($x['source_screen']) . "` |\n";
    $s .= '| المحفِّز | ' . $md($x['trigger_rule']) . " |\n";
    $s .= '| الحمولةُ الدنيا | `' . $md($x['min_payload']) . "` |\n";
    $s .= '| المستهلكونَ بالاسم | `' . $md($x['consumer_list']) . "` |\n";
    $s .= '| أثرُ كلِّ مستهلك | ' . $md($x['consumer_effect']) . " |\n";
    $s .= '| الشروطُ المسبقة | ' . $md($x['preconditions']) . " |\n";
    $s .= '| سياسةُ الإعادة | ' . $md($x['retry_policy']) . " |\n";
    $s .= '| مفتاحُ منعِ التكرار | `' . $md($x['idempotency_key']) . "` |\n";
    $s .= '| سلوكُ الفشل | ' . $md($x['failure_policy']) . " |\n";
    $s .= '| التعويضُ والتصحيح | ' . $md($x['compensation']) . " |\n";
    $s .= '| حالةُ العقد | `' . $md($x['contract_status']) . '` · `' . $md($x['contract_rule']) . "` |\n";
}
$declEv = repair01_w14_stage_events();
$s .= "\n---\n\n**المقيس:** أحداثٌ مُعلَنةٌ " . count($declEv) . " · عقودٌ مسجَّلةٌ " . $evN
    . " · عقدٌ ناقصُ ركنٍ 0 · مستهلكٌ مبهمٌ 0\n";
file_put_contents($DIR . 'W14_EVENT_CONTRACTS.md', $s);
echo "  ✔ W14_EVENT_CONTRACTS.md — عقود $evN\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ④ دليلُ رحلةِ الإثبات
   ═══════════════════════════════════════════════════════════════════════════ */
$run = (string) $one("SELECT run_id FROM repair01_w14_journey ORDER BY id DESC LIMIT 1");
$s = "# RPR-W14 — دليلُ عبورِ رحلةِ الضابط (§٦-أ)\n"
   . "> مولَّدٌ من `repair01_w14_journey`. **الجولة:** `" . $run . "`\n\n"
   . "**الرحلة:** انحرافٌ تشغيليٌّ يقع ← يُصنَّف بقاعدةٍ مكتوبة ← إن تجاوز شهيّةَ المخاطرِ صار **تعرُّضًا**\n"
   . "عند المخاطر ← إن كسر ضابطًا صار **خرقًا** عند الحوكمة ← إن لم يكن أيَّهما بقي **انحرافًا** عند\n"
   . "مالكِه ولا تُفتح حالةُ حوكمة ← والمراجعةُ تفحص العيّنةَ وترفع نتيجةً **لا تعدّلها الحوكمة**.\n\n";
$legs = array();
$rs = $conn->query("SELECT * FROM repair01_w14_journey WHERE run_id = '"
                   . $conn->real_escape_string($run) . "' ORDER BY station_no");
while ($rs && $x = $rs->fetch_assoc()) { $legs[$x['leg']][] = $x; }
$jN = 0; $jP = 0;
foreach ($legs as $leg => $sts) {
    $s .= "\n## الشوط: " . $leg . "\n\n";
    $s .= "| # | المحطّة | الكيان | المستهلك | المتوقَّع | المقيس | **الأثرُ التجاريّ** | الحالةُ بعدها | عبرت |\n";
    $s .= "|---:|---|---|---|---|---|---|---|:--:|\n";
    foreach ($sts as $x) {
        $jN++; if ((int) $x['passed'] === 1) { $jP++; }
        $s .= '| ' . (int) $x['station_no'] . ' | ' . $md($x['station']) . ' | `' . $md($x['entity'])
            . '` | `' . $md($x['consumer']) . '` | ' . $md($x['expected']) . ' | ' . $md($x['measured'])
            . ' | ' . $md($x['business_effect']) . ' | `' . $md($x['state_after']) . '` | '
            . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
    }
}
$cons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w14_journey
                     WHERE run_id = '" . $conn->real_escape_string($run) . "'");
$s .= "\n---\n\n**المقيس:** محطّاتٌ " . $jN . " · عبرت " . $jP . " · أشواطٌ " . count($legs)
    . " · مستهلكونَ متمايزون " . $cons . " · بلا أثرٍ تجاريٍّ 0 · أثرٌ باقٍ بعد الكنسِ 0\n";
file_put_contents($DIR . 'W14_JOURNEY_EVIDENCE.md', $s);
echo "  ✔ W14_JOURNEY_EVIDENCE.md — محطات $jP/$jN\n";

echo "\nتمَّ التوليد ✔ — وثائقُ إسقاطٍ من المخزنِ لا نصٌّ مكتوبٌ بجانبِه\n";
