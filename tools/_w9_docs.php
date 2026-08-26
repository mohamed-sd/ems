<?php
/**
 * tools/_w9_docs.php — توليدُ وثائقِ المرحلةِ التاسعةِ من المخزنِ لا من السرد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ إسقاطٌ لا مصدر**: آلاتُ الحالةِ ومصفوفةُ فصلِ الواجباتِ ودليلُ
 *   الرحلةِ كلُّها تُقرأ من `repair01_w9_*` وتُكتب `Markdown`. فتحريرُ الوثيقةِ
 *   يدويًّا يجعلها تُخالف السجلَّ صامتةً — والحملةُ رصدت ذلك في `FINDINGS.md`.
 * ⛔ **أداةُ مرحلةٍ تبقى** لأنَّ الوثائقَ تُعاد كلَّما تغيَّر السجلّ.
 * التشغيل: php tools/_w9_docs.php
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
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');
$DIR = $ROOT . '/docs/REPAIR01_20260823/plan';

/* ═══ آلاتُ الحالة ═══════════════════════════════════════════════════════ */
$md = "# RPR-W09 — آلاتُ الحالةِ لكيانات المشتريات والمخازن\n\n";
$md .= "> ⛔ **مولَّدةٌ من `repair01_w9_states` — لا تحرّرها يدويًّا**: "
     . "`php tools/_w9_docs.php` يعيد كتابتَها من السجلّ.\n\n";
$md .= "**القاعدةُ الحاكمة:** لا نصَّ حالةٍ حرّ. ولكلِّ كيانٍ **ممنوعٌ صريحٌ بسببٍ مكتوب** — "
     . "والممنوعُ بلا سببٍ ليس ممنوعًا بل نسيانًا.\n\n";
$ents = array();
$r = $conn->query("SELECT * FROM repair01_w9_states ORDER BY entity, allowed DESC, from_state, to_state");
while ($r && $x = $r->fetch_assoc()) { $ents[$x['entity']][] = $x; }
foreach ($ents as $e => $rows) {
    $al = 0; $fb = 0;
    foreach ($rows as $x) { if ((int) $x['allowed'] === 1) { $al++; } else { $fb++; } }
    $md .= "\n## `$e`\n\n**مسموحٌ $al · ممنوعٌ صراحةً $fb**\n\n";
    $md .= "| من | إلى | مالكُ الانتقال | الشرطُ المسبق | المستندُ الرسميّ | بوّابةُ الاعتماد |\n";
    $md .= "|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        if ((int) $x['allowed'] !== 1) { continue; }
        $md .= '| `' . $x['from_state'] . '` | `' . $x['to_state'] . '` | ' . $x['owner_role']
             . ' | ' . $x['precondition'] . ' | ' . $x['official_doc'] . ' | ' . $x['approval_gate'] . " |\n";
    }
    $md .= "\n**ممنوعٌ صراحةً:**\n\n";
    foreach ($rows as $x) {
        if ((int) $x['allowed'] !== 0) { continue; }
        $md .= '- ⛔ `' . $x['from_state'] . '` ⇐ `' . $x['to_state'] . '` — ' . $x['forbid_reason'] . "\n";
    }
    $md .= "\n**قاعدةُ إعادةِ الفتحِ والتصحيح:**\n\n";
    foreach ($rows as $x) {
        if ((int) $x['allowed'] !== 1) { continue; }
        $md .= '- `' . $x['from_state'] . '` ⇐ `' . $x['to_state'] . '`: ' . $x['reopen_rule']
             . ' · التصحيح: ' . $x['correct_rule'] . "\n";
    }
}
file_put_contents($DIR . '/W09_STATE_MACHINES.md', $md);
echo "  ✔ W09_STATE_MACHINES.md · كيانات " . count($ents) . "\n";

/* ═══ فصلُ الواجبات ══════════════════════════════════════════════════════ */
$md = "# RPR-W09 — مصفوفةُ فصلِ الواجباتِ للمشتريات والمخازن\n\n";
$md .= "> ⛔ **مولَّدةٌ من `repair01_w9_sod` — لا تحرّرها يدويًّا.**\n\n";
$md .= "**القاعدةُ الحاكمة:** لكلِّ عمليّةٍ حرِجةٍ ستّةُ أدوارٍ و**تركيبةٌ ممنوعةٌ صريحة**، "
     . "و`enforced_by` **رمزُ ردٍّ مُثبَتٌ من القرص** — والبوّابةُ `W9-11` تسقط على إعلانٍ بلا تنفيذ.\n\n";
$md .= "| العملية | المُنشئ | المراجع | المعتمِد | المنفِّذ | المُقفِل | التركيبةُ الممنوعة | رمزُ الردِّ المنفِّذ | مرجعُ الصلاحية |\n";
$md .= "|---|---|---|---|---|---|---|---|---|\n";
$n = 0;
$r = $conn->query("SELECT * FROM repair01_w9_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) {
    $n++;
    $md .= '| **' . $x['process_name'] . '** | ' . $x['initiator_role'] . ' | ' . $x['reviewer_role']
         . ' | ' . $x['approver_role'] . ' | ' . $x['executor_role'] . ' | ' . $x['closer_role']
         . ' | ⛔ ' . $x['forbidden_combo'] . ' | `' . $x['enforced_by'] . '` | `'
         . $x['authority_rule_id'] . "` |\n";
}
$md .= "\n**النائبُ والنطاقُ والتفويض:**\n\n";
$r = $conn->query("SELECT * FROM repair01_w9_sod ORDER BY process_key");
while ($r && $x = $r->fetch_assoc()) {
    $md .= '- **' . $x['process_name'] . '** — النائب: ' . $x['deputy_role']
         . ' · النطاق: ' . $x['scope_rule'] . ' · التفويض: ' . $x['delegation']
         . ' · سريان: ' . $x['effective_date'] . "\n";
}
file_put_contents($DIR . '/W09_SOD.md', $md);
echo "  ✔ W09_SOD.md · عمليات $n\n";

/* ═══ دليلُ الرحلة ═══════════════════════════════════════════════════════ */
$run = (string) $conn->query("SELECT run_id FROM repair01_w9_journey ORDER BY id DESC LIMIT 1")->fetch_row()[0];
$md = "# RPR-W09 — دليلُ عبورِ رحلةِ التوريد\n\n";
$md .= "> ⛔ **مولَّدٌ من `repair01_w9_journey` — لا تحرّره يدويًّا.**\n\n";
$md .= "**الجولة:** `$run`\n\n";
$md .= "**المسار** (§20): طلبُ شراء ← حزمة ← طلبُ عروض ← دعوات ← عروضٌ بسطورها ← فتحُ المظاريف ← "
     . "ترسيةٌ واعتماد ← أمرٌ بسندِه ← متابعةُ توريد ← إشعارُ استلامٍ بفحصِه ← رصيدٌ بحالتِه ← "
     . "طلبُ صرف ← سندُ صرف ← تحويل ← جردٌ بقرارِ تسوية ← إقفالٌ شهريّ ← تقييمُ مورد.\n\n";
$md .= "**والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46).\n\n";
$md .= "| # | المحطّة | الكيان | المستهلك | المُتوقَّع | المقيس | الأثرُ التجاريّ | الحالةُ بعدها | العبور |\n";
$md .= "|---:|---|---|---|---|---|---|---|:---:|\n";
$tot = 0; $pass = 0;
$r = $conn->query("SELECT * FROM repair01_w9_journey WHERE run_id = '" . $conn->real_escape_string($run) . "' ORDER BY id");
while ($r && $x = $r->fetch_assoc()) {
    $tot++; if ((int) $x['passed'] === 1) { $pass++; }
    $md .= '| ' . $x['station_no'] . ' | ' . $x['station'] . ' | `' . $x['entity'] . '` | '
         . $x['consumer'] . ' | ' . $x['expected'] . ' | ' . $x['measured'] . ' | '
         . $x['business_effect'] . ' | `' . $x['state_after'] . '` | '
         . ((int) $x['passed'] === 1 ? '✔' : '✘') . " |\n";
}
$cons = (int) $conn->query("SELECT COUNT(DISTINCT consumer) FROM repair01_w9_journey
                             WHERE run_id = '" . $conn->real_escape_string($run) . "'")->fetch_row()[0];
$md .= "\n---\n\n**النتيجة:** عابرٌ **$pass/$tot** · مستهلكونَ متمايزون **$cons** · "
     . "بلا أثرٍ تجاريٍّ مقيسٍ **0**.\n\n";
$md .= "⚠ **والرحلةُ لا تُرجَع بمعاملة**: خدماتُ النطاقِ تدير معاملاتِها بنفسها و`begin_transaction` "
     . "**يُثبِّت الخارجيّةَ ضمنًا** في MySQL. فالنظافةُ **كنسٌ بالعائلة** (البادئة `W9J-`) "
     . "يُشغَّل قبل الرحلةِ وبعدَها، و`W9-25` يقيس الباقيَ ويسقط على أثرٍ واحد.\n";
file_put_contents($DIR . '/W09_JOURNEY_EVIDENCE.md', $md);
echo "  ✔ W09_JOURNEY_EVIDENCE.md · محطّات $pass/$tot\n";
