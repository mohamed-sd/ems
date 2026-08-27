<?php
/**
 * tools/repair01_w16_docs.php — إسقاطُ دفاترِ W16 إلى وثائقَ على القرص
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **الوثيقةُ إسقاطٌ لا مصدر**: أيُّ فرقٍ بينها وبين المخزنِ يُصلَح في المخزن
 *   ثمَّ يُعاد التوليد. ⛔ **ولا تُحرَّر يدويًّا.**
 *
 * ⛔ **ولا نسبةَ مجمَّعةً واحدة** (‏البندُ ٦٤): كلُّ خليّةٍ **بسطٌ من مقام**،
 *   والمقامُ مطبوعٌ باسمِه في عمودِه — و«٪ مكتمل» لا تُكتب في أيِّ صفّ.
 *
 * التشغيل: php tools/repair01_w16_docs.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w16_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$DIR = $ROOT . '/docs/REPAIR01_20260823/';
$one = function ($sql) use ($conn) { return repair01_w16_one($conn, $sql); };
$all = function ($sql) use ($conn) {
    $o = array(); $r = @$conn->query($sql);
    while ($r && ($x = $r->fetch_assoc())) { $o[] = $x; }
    return $o;
};
$w = function ($name, $body) use ($DIR) {
    file_put_contents($DIR . $name, $body);
    echo "  ✔ كُتب docs/REPAIR01_20260823/$name\n";
};

$snap   = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1");
$commit = trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD 2>&1'));
$stamp  = "| `Snapshot ID` | `" . $snap . "` |\n| `Commit Hash` | `" . $commit . "` |\n"
        . "| `Measured At` | " . date('Y-m-d H:i:s') . " |\n";

/* ═══ ① الطبقاتُ الثمانية ═══════════════════════════════════════════════ */
$o  = "# الطبقاتُ الثمانيةُ التي يشترطها إصدارُ الأساس — البندُ ٥٦\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`\n";
$o .= "> **نصُّ البند**: «لا يصدر `Enterprise Target Baseline` إلّا بعد أن تنجح» الثمانيةُ أدناه.\n";
$o .= "> **ولكلِّ طبقةٍ مقامُها المطبوعُ بجانبِها** — ⛔ ولا نسبةَ مجمَّعةً واحدة.\n\n";
$o .= "| الحقل | القيمة |\n|---|---|\n" . $stamp . "\n";
$o .= "| # | الطبقة | المقيس | المقام | اسمُ المقام | الحكم |\n|---:|---|---:|---:|---|---|\n";
$lp = 0; $lt = 0;
foreach ($all("SELECT * FROM repair01_w16_layers ORDER BY layer_no") as $x) {
    $lt++;
    if ($x['verdict'] === 'PASS') { $lp++; }
    $o .= sprintf("| %d | %s | %d | %d | %s | %s |\n", $x['layer_no'], $x['layer_name_ar'],
        $x['measured_num'], $x['measured_den'], $x['den_name'],
        $x['verdict'] === 'PASS' ? '✔' : ($x['verdict'] === 'FAIL' ? '✘' : '◆ غيرُ مقيس'));
}
$o .= "\n**عابرةٌ $lp من $lt** — وكلُّ صفٍّ يحمل استعلامَ قياسِه في `repair01_w16_layers.measure_sql`،\n";
$o .= "والبوّابةُ `W16-03` **تُعيد تشغيلَه** ولا تقرأ هذا الجدول.\n";
$w('W16_LAYERS.md', $o);

/* ═══ ② لوحةُ المقاماتِ التسعة ══════════════════════════════════════════ */
$AX = array();
foreach ($all("SELECT * FROM repair01_w16_axes ORDER BY axis_no") as $a) { $AX[$a['axis_key']] = $a; }
$DOM = repair01_w16_domains();
$names = array();
foreach ($all("SELECT canonical_code, name_ar FROM repair01_departments") as $d) {
    $names[$d['canonical_code']] = $d['name_ar'];
}
$names['PLATFORM'] = 'أسطح المنصة المشتركة (ليست إدارة من السبع عشرة)';

$o  = "# المقاماتُ التسعةُ لكلِّ نطاق — البندُ ٦٤\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`\n";
$o .= "> **نصُّ البند**: «لا تقبل نسبةً واحدة… ولا تكتب «٩٥٪» دون هذه المقامات».\n";
$o .= "> **فكلُّ خليّةٍ بسطٌ من مقام** — ⛔ **ولا نسبةَ مجمَّعةٌ واحدةٌ في هذا الملفّ ولا في غيرِه**.\n";
$o .= "> و`—` تعني **غيرَ مقيسٍ بإعلان**، ⛔ **لا صفرًا**: «صفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا».\n\n";
$o .= "| الحقل | القيمة |\n|---|---|\n" . $stamp . "\n";

$o .= "## تعريفُ المحاورِ التسعةِ وحدِّ أداةِ كلِّ محور\n\n";
$o .= "| # | المفتاح | المحور | البسط | المقام | ماذا **لا** تقيس |\n|---:|---|---|---|---|---|\n";
foreach ($AX as $a) {
    $o .= sprintf("| %d | `%s` | %s | %s | %s | %s |\n", $a['axis_no'], $a['axis_key'],
        $a['axis_name_ar'], $a['num_rule'], $a['den_rule'], $a['instrument']);
}

$o .= "\n## اللوحة — نطاقٌ × محور\n\n| النطاق | ";
foreach ($AX as $a) { $o .= $a['axis_name_ar'] . ' | '; }
$o .= "\n|---|" . str_repeat("---:|", count($AX)) . "\n";
$cells = array();
foreach ($all("SELECT * FROM repair01_w16_scorecard") as $s) { $cells[$s['domain_code']][$s['axis_key']] = $s; }
foreach ($DOM as $d) {
    $o .= sprintf("| `%s` %s | ", $d, isset($names[$d]) ? $names[$d] : '');
    foreach ($AX as $k => $a) {
        $c = isset($cells[$d][$k]) ? $cells[$d][$k] : null;
        if (!$c || $c['verdict'] === 'NOT_MEASURED') { $o .= '— | '; }
        else { $o .= $c['num'] . ' / ' . $c['den'] . ' | '; }
    }
    $o .= "\n";
}
$o .= "\n## أسماءُ المقاماتِ وأسبابُ غيرِ المقيس\n\n";
$o .= "| النطاق | المحور | البسط | المقام | اسمُ المقام أو سببُ عدمِ القياس |\n|---|---|---:|---:|---|\n";
foreach ($DOM as $d) {
    foreach ($AX as $k => $a) {
        $c = isset($cells[$d][$k]) ? $cells[$d][$k] : null;
        if (!$c) { continue; }
        $o .= sprintf("| `%s` | %s | %s | %s | %s |\n", $d, $a['axis_name_ar'],
            $c['verdict'] === 'MEASURED' ? $c['num'] : '—',
            $c['verdict'] === 'MEASURED' ? $c['den'] : '—',
            $c['verdict'] === 'MEASURED' ? ($c['den_name'] . ($c['note'] !== '' ? ' · ' . $c['note'] : ''))
                                         : ('**غيرُ مقيسٍ**: ' . $c['note']));
    }
}
$nm = (int) $one("SELECT COUNT(*) FROM repair01_w16_scorecard WHERE verdict = 'NOT_MEASURED'");
$o .= "\n**صفوفٌ منشورةٌ " . (count($DOM) * 9) . " · منها غيرُ مقيسٍ بإعلانٍ $nm** — ⛔ ولا واحدٌ منها مكتوبٌ صفرًا.\n";
$w('W16_SCORECARD.md', $o);

/* ═══ ③ المراجعةُ الثانيةُ المستقلّة ═══════════════════════════════════ */
$o  = "# المراجعةُ الثانيةُ كتحدٍّ مستقلّ — البندُ ٥٠\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_challenge.php --apply` ثمَّ `repair01_w16_docs.php`\n";
$o .= "> **نصُّ البند**: «ولا تجعله يعيد استخدام نفس `Rule Engine` الذي بنى `Target`.\n";
$o .= "> يجب أن يستطيع إصدارَ `REDESIGN` حتى إذا الاختباراتُ البنيويّةُ خضراء».\n";
$o .= "> **فمصادرُ هذا المحرّكِ أوّليّةٌ وحدَها**: القرصُ · المخطَّطُ الحيُّ · المصنَّفاتُ المجمَّدة.\n";
$o .= "> والحاجبُ `W16-16` يسقط إن قرأ المحرّكُ دفترَ موجةٍ من التي بنت الهدف.\n\n";
$o .= "| الحقل | القيمة |\n|---|---|\n" . $stamp . "\n";
$o .= "| المُعرِّف | القاعدة | الشدّة | المقيس | المقام | المصدرُ الأوّليّ |\n|---|---|---|---|---|---|\n";
foreach ($all("SELECT * FROM repair01_w16_challenge ORDER BY finding_id") as $f) {
    $o .= sprintf("| `%s` | %s | `%s` | %s | %s | %s |\n", $f['finding_id'], $f['title'],
        $f['severity'], $f['measured'], $f['subject'], $f['primary_source']);
}
$red = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'REDESIGN'");
$con = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'CONCERN'");
$acc = (int) $one("SELECT COUNT(*) FROM repair01_w16_challenge WHERE severity = 'ACCEPT'");
$o .= "\n**`ACCEPT` $acc · `CONCERN` $con · `REDESIGN` $red** — والحكمُ العامُّ **"
    . ($red > 0 ? 'REDESIGN' : ($con > 0 ? 'CONCERN' : 'ACCEPT')) . "**\n\n";
$o .= "### شواهدُ القواعد\n\n| المُعرِّف | كيف قيست |\n|---|---|\n";
foreach ($all("SELECT finding_id, evidence FROM repair01_w16_challenge ORDER BY finding_id") as $f) {
    $o .= sprintf("| `%s` | %s |\n", $f['finding_id'], $f['evidence']);
}
$w('W16_CHALLENGE.md', $o);

/* ═══ ④ رحلةُ الموظّفِ الحقيقيّ ════════════════════════════════════════ */
$uN  = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat");
$uOk = (int) $one("SELECT COUNT(*) FROM repair01_w16_uat WHERE status = 'PASSED'");
$o  = "# رحلةُ الموظّفِ الحقيقيِّ — البندُ ٦٣\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`\n";
$o .= "> **نصُّ البند**: «موظّفٌ فعليٌّ أو `UAT User` يمثّل دورَه الحقيقيَّ يمرُّ الرحلةَ\n";
$o .= "> من بدايتها لنهايتها. **ولا `Seed Data` فقط**».\n";
$o .= "> **والمحطّاتُ مسجَّلةٌ مُنتظِرةً** — ⛔ **ولا تكتب أداةٌ `PASSED`**: القيدُ\n";
$o .= "> `chk_w16_uat_real` **يردُّ في القاعدةِ** إعلانَ نجاحٍ بلا فاعلٍ حقيقيٍّ وزمنٍ ودليل،\n";
$o .= "> و`chk_w16_uat_negative` يردُّ مسارًا سالبًا عابرًا بلا قيدٍ في سجلِّ المحاولات.\n\n";
$o .= "| الحقل | القيمة |\n|---|---|\n" . $stamp . "\n";
$o .= "**محطّاتٌ مسجَّلةٌ $uN · عبرها إنسانٌ $uOk** — والمقامُ مطبوعٌ ولا يُدَّعى غيرُه.\n\n";
$o .= "| المحطّة | الرحلة | # | ما يفعله الإنسان | النطاق | الدورُ المطلوب | خانةُ الشخص | سالب | الحال |\n";
$o .= "|---|---|---:|---|---|---|---|---|---|\n";
foreach ($all("SELECT * FROM repair01_w16_uat ORDER BY journey_key, station_no") as $u) {
    $o .= sprintf("| `%s` | %s | %d | %s | `%s` | %s | %s | %s | %s |\n",
        $u['station_id'], $u['journey_key'], $u['station_no'], $u['station_ar'],
        $u['domain_code'], $u['required_role'], $u['person_slot'],
        $u['is_negative'] ? 'نعم' : '—', $u['status'] === 'PASSED' ? '✔ عبرت' : 'مُنتظِرة');
}
$o .= "\n### ما يلزم لإعلانِ محطّةٍ عابرة\n\n";
$o .= "1. `actor_user_id` **مستخدمٌ قائمٌ في سجلِّ المستخدمين** — والحاجبُ `W16-10` يتحقّق منه.\n";
$o .= "2. `acted_at` زمنُ الفعل · 3. `evidence_ref` دليلُ المحطّة · 4. `actor_name` هويّةُ فاعلِها.\n";
$o .= "5. وللمسارِ السالب: `attempt_log_ref` **قيدُ المحاولةِ الممنوعةِ في سجلِّ المحاولات**.\n";
$o .= "6. **وثلاثةُ أشخاصٍ مختلفين** — الحاجبُ `W16-12` يسقط إن شغل حسابٌ واحدٌ خانتَين.\n";
$w('W16_UAT_JOURNEY.md', $o);

/* ═══ ⑤ حكمُ التبويباتِ الموروثة ═══════════════════════════════════════ */
$o  = "# الدَّينُ الموروثُ `DC-13` — سبعةٌ وخمسون تبويبًا بحكمٍ مسجَّلٍ فرادى\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`\n";
$o .= "> **حكمُ المالك**: «لا نضحّي بالصلاحيةِ من أجلِ سايدبارٍ أنظف — والثمانيةُ والخمسون تُحكم فرادى».\n";
$o .= "> **ومعيارُ الخروج**: «كلُّ تبويبٍ يُحكم على حدة **بقرارٍ مسجَّل**» — فهذا الدفترُ هو معيارُ الخروجِ نفسُه.\n";
$o .= "> ⛔ **ولم يُدمَج بندٌ ولم يُخفَ**: الدمجُ تغييرُ ملاحةٍ حيٍّ بقرارِ مالك، **والمسجَّلُ هنا حكمٌ لا تنفيذ**.\n\n";
$o .= "| الحقل | القيمة |\n|---|---|\n" . $stamp . "\n";
$o .= "| التصرُّفُ المسجَّل | العدد | معناه |\n|---|---:|---|\n";
$DISP = array(
 'KEEP_ITEM'          => 'يبقى بندًا مستقلًّا — الدمجُ يسلب أدوارًا وصولَها',
 'MERGE_INTO_PARENT'  => 'مؤهَّلٌ للدمجِ صلاحيًّا وأبوّتُه مقيسةٌ — **والتنفيذُ خطوةٌ تالية**',
 'PARENT_RAISED'      => 'الأبوّةُ المسجَّلةُ مشكوكةٌ تُرفع ولا يُبنى عليها',
 'GRANT_GAP_TO_OWNER' => 'مخفيٌّ والمنحُ حيّ — **فجوةُ وصولٍ سابقةٌ** خروجُها بقرارِ مالك',
 'RETIRE'             => 'يتقاعد بخلفِه المُعلَن');
foreach ($DISP as $k => $v) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_w16_tabs WHERE disposition = '" . $conn->real_escape_string($k) . "'");
    if ($n === 0) { continue; }
    $o .= sprintf("| `%s` | %d | %s |\n", $k, $n, $v);
}
$o .= "\n| التبويب | الإدارة | الأب | حكمُ الأداة | التصرُّفُ المسجَّل | مرجعُ المالك | السبب |\n";
$o .= "|---|---|---|---|---|---|---|\n";
foreach ($all("SELECT * FROM repair01_w16_tabs ORDER BY dept_code, screen_file") as $t) {
    $o .= sprintf("| `%s` | %s | `%s` | `%s` | `%s` | %s | %s |\n", $t['screen_file'], $t['dept_code'],
        $t['parent_file'] !== '' ? basename($t['parent_file']) : '—', $t['judged_verdict'],
        $t['disposition'], $t['owner_ref'] !== '' ? '`' . $t['owner_ref'] . '`' : '—', $t['why']);
}
$w('W16_TABS_DISPOSITION.md', $o);

/* ═══ ⑥ وثيقةُ الإصدار ═════════════════════════════════════════════════ */
$bl = null;
$r = $conn->query("SELECT * FROM repair01_w16_baseline ORDER BY issued_at DESC LIMIT 1");
if ($r && $r->num_rows) { $bl = $r->fetch_assoc(); }
$o  = "# ENTERPRISE-TARGET-BASELINE-v1.0\n\n";
$o .= "> ⛔ **مولَّدٌ من المخزن**: `php tools/repair01_w16_docs.php`\n";
$o .= "> **البندُ ٥٦**: لا يصدر الأساسُ إلّا بعد أن تنجح الثمانية. **والبندُ ⑩ من أمرِ المالك**:\n";
$o .= "> «**والإغلاقُ قرارُ مالكٍ لا نتيجةُ أداة**» — فهذه الوثيقةُ تُثبت استيفاءَ الشروطِ لا غير.\n\n";
if (!$bl) {
    $o .= "⛔ **لا صفَّ إصدارٍ في المخزن** — شغّلْ `php tools/repair01_w16_apply.php --issue`.\n";
} else {
    $o .= "| الحقل | القيمة |\n|---|---|\n";
    $o .= "| `Baseline ID` | `" . $bl['baseline_id'] . "` |\n";
    $o .= "| `Version` | `" . $bl['version'] . "` |\n";
    $o .= "| **`State`** | **`" . $bl['state'] . "`** |\n";
    $o .= "| `Snapshot ID` | `" . $bl['snapshot_id'] . "` |\n";
    $o .= "| `Commit Hash` | `" . $bl['commit_hash'] . "` |\n";
    $o .= "| الطبقاتُ العابرة | " . $bl['layers_pass'] . " من " . $bl['layers_total'] . " |\n";
    $o .= "| حكمُ المراجعةِ المستقلّة | `" . $bl['challenge_verdict'] . "` ("
        . "`REDESIGN` " . $bl['redesign_count'] . " · `CONCERN` " . $bl['concern_count'] . ") |\n";
    $o .= "| `Owner Stamp` | " . ($bl['owner_ref'] !== '' ? '`' . $bl['owner_ref'] . '`'
                                : '**لم يُختَم بعد — وهو قرارُ المالك**') . " |\n";
    $o .= "| `Issued At` | " . $bl['issued_at'] . " |\n\n";
    $o .= "**لماذا هذه الحالة:** " . $bl['why'] . "\n\n";
}
$o .= "## الثمانيةُ المنصوصةُ في البندِ ٥٦\n\n";
$o .= "| # | الطبقة | المقيس / المقام | الحكم |\n|---:|---|---:|---|\n";
foreach ($all("SELECT * FROM repair01_w16_layers ORDER BY layer_no") as $x) {
    $o .= sprintf("| %d | %s | %d / %d | %s |\n", $x['layer_no'], $x['layer_name_ar'],
        $x['measured_num'], $x['measured_den'], $x['verdict'] === 'PASS' ? '✔' : '✘');
}
$o .= "\n## ما لا يدَّعيه هذا الأساس\n\n";
$o .= "- **`Data Ready`** — محورُ البيانات `NOT_MEASURED` في **" . count($DOM) . " نطاقًا**: لا أداةَ قياسٍ بنتها الحملة.\n";
$o .= "- **`Human UAT`** — **$uOk من $uN محطّةً** عبرها إنسان. ⛔ ولا يُخضِرُّها سكربت.\n";
$o .= "- **`Acceptance`** — صفرٌ في كلِّ نطاق **بحكمِ تعريفِه**: القبولُ يلزمه القبولُ البشريُّ أوّلًا.\n";
$o .= "- **`Owner Approved`** — **ختمُ المالكِ لم يُوضَع**، والقاعدةُ `chk_w16_bl_owner` تردُّ وضعَه بلا مرجع.\n\n";
$o .= "## أين المصنَّفاتُ الاثنا عشرَ المُعادُ توليدُها\n\n";
$o .= "`docs/REPAIR01_20260823/baseline_v1/` — **إسقاطٌ من المخزنِ يحمل أحكامَ الموجاتِ الستَّ عشرة**.\n";
$o .= "⛔ **ولم تُكتب فوق المصنَّفاتِ المُجمَّدة**: الثلاثةَ عشرَ مختومةٌ بتجزئةِ `sha256`،\n";
$o .= "والحاجبُ `G0-01` يعيد تجزئتَها في كلِّ تشغيل — **فالكتابةُ فوقها تمحو دليلَ الدخولِ**\n";
$o .= "وتُسقط أساسَ الحملةِ كلِّها. والقرارُ مسجَّلٌ في `W16-D-02`.\n";
$w('ENTERPRISE_TARGET_BASELINE_v1.0.md', $o);

echo "\n✔ تمّ\n";
