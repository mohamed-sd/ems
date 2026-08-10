<?php
/**
 * tools/fix_inj0013_manifest_actions.php — INJ-0013: زرعُ عقودِ الأفعالِ في البيان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الإصلاحُ الموصى به في السجلِّ الجامعِ حرفيًّا: «أضِف في
 *   `tools/u13_screens_manifest.php` عقودَ الأفعالِ الناقصةَ وأعِد التوليد».
 *   فالشاشاتُ **مولَّدةٌ** ولا تُحرَّر يدويًّا — والتحريرُ اليدويُّ يُمحى عند
 *   أولِ إعادةِ توليد.
 *
 * ◆ يُحقن `actions_src` في صفِّ كلِّ شاشةٍ من البيان (idempotent: لا يُضاف
 *   لصفٍّ يحمله سلفًا)، ثم يُشغَّل المولِّد.
 *
 * التشغيل: php tools/fix_inj0013_manifest_actions.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$MAN  = $ROOT . '/tools/u13_screens_manifest.php';
$apply = in_array('--apply', $argv, true);

/** عقودُ الأفعالِ الثمانيةُ + إقرارُ الاستقلال (شرطُ فتحِ المهمة). */
$ACTIONS = array(
'iaf_charter' => array(array(
    'key' => 'approve', 'code' => 'iaf.charter.approve', 'label' => 'اعتمادُ الميثاق',
    'rule' => 'IAF-0044: ولا كونَ رقابيٌّ بلا ميثاقٍ معتمد',
    'fields' => array('version' => 'نسخةُ الميثاق'),
    'call' => 'approveCharter', 'args' => "'version' => (string) (\$in['version'] ?? ''), 'actor' => \$uid",
)),
'iaf_independence' => array(array(
    'key' => 'declare', 'code' => 'iaf.independence.declare', 'label' => 'إقرارُ استقلالٍ وتعارضِ مصالح',
    'rule' => 'IAF-0009: لا تكليفَ بمهمةٍ بلا إقرارِ استقلالٍ سارٍ',
    'fields' => array('auditor_id' => 'رقمُ المراجع', 'scope_ref' => 'النطاق', 'valid_until' => 'سارٍ حتى'),
    'call' => 'declareIndependence',
    'args' => "'auditor_id' => (int) (\$in['auditor_id'] ?? 0), 'scope_ref' => (string) (\$in['scope_ref'] ?? ''),\n"
            . "                    'valid_until' => (string) (\$in['valid_until'] ?? ''), 'has_conflict' => !empty(\$in['has_conflict'])",
)),
'iaf_universe' => array(array(
    'key' => 'build', 'code' => 'iaf.universe.build', 'label' => 'إدراجُ مجالٍ في الكونِ الرقابي',
    'rule' => 'IAF-0044: لا كونَ بلا ميثاقٍ معتمد',
    'fields' => array('area_code' => 'رمزُ المجال', 'area_name' => 'اسمُ المجال', 'owner_dept' => 'الإدارةُ المالكة', 'risk_score' => 'درجةُ الخطر'),
    'call' => 'buildUniverse',
    'args' => "'area_code' => (string) (\$in['area_code'] ?? ''), 'area_name' => (string) (\$in['area_name'] ?? ''),\n"
            . "                    'owner_dept' => (string) (\$in['owner_dept'] ?? ''), 'risk_score' => (int) (\$in['risk_score'] ?? 0)",
)),
'iaf_plan' => array(array(
    'key' => 'approve', 'code' => 'iaf.plan.approve', 'label' => 'اعتمادُ الخطةِ السنوية',
    'rule' => 'IAF-0044: لا خطةَ بلا كونٍ رقابيٍّ مبنيّ',
    'fields' => array('plan_year' => 'سنةُ الخطة', 'title' => 'عنوانُ الخطة', 'basis' => 'الأساس'),
    'call' => 'approvePlan',
    'args' => "'plan_year' => (int) (\$in['plan_year'] ?? 0), 'title' => (string) (\$in['title'] ?? ''),\n"
            . "                    'basis' => (string) (\$in['basis'] ?? ''), 'actor' => \$uid",
)),
'iaf_engagements' => array(array(
    'key' => 'open', 'code' => 'iaf.engagement.open', 'label' => 'فتحُ مهمةِ مراجعة',
    'rule' => 'IAF-0044 + IAF-0009: لا مهمةَ بلا خطةٍ معتمدةٍ ولا بلا إقرارِ استقلالٍ سارٍ',
    'fields' => array('plan_id' => 'رقمُ الخطة', 'area_code' => 'رمزُ المجال', 'title' => 'عنوانُ المهمة', 'lead_auditor' => 'المراجعُ المكلَّف'),
    'call' => 'openEngagement',
    'args' => "'plan_id' => (int) (\$in['plan_id'] ?? 0), 'area_code' => (string) (\$in['area_code'] ?? ''),\n"
            . "                    'title' => (string) (\$in['title'] ?? ''), 'lead_auditor' => (int) (\$in['lead_auditor'] ?? 0)",
)),
'iaf_workpapers' => array(array(
    'key' => 'attach', 'code' => 'iaf.workpaper.attach', 'label' => 'إرفاقُ ورقةِ عملٍ ببصمةِ دليل',
    'rule' => 'IAF-0037: نسخُ أدلةٍ غيرُ قابلةٍ للتعديلِ ببصمةِ كلٍّ — والبصمةُ تُحسب لا تُدخَل',
    'fields' => array('engagement_no' => 'رقمُ المهمة', 'wp_ref' => 'مرجعُ الورقة', 'title' => 'عنوانُ الورقة'),
    'call' => 'attachWorkpaper',
    'args' => "'engagement_no' => (string) (\$in['engagement_no'] ?? ''), 'wp_ref' => (string) (\$in['wp_ref'] ?? ''),\n"
            . "                    'title' => (string) (\$in['title'] ?? ''), 'actor' => \$uid",
)),
'iaf_responses' => array(array(
    'key' => 'submit', 'code' => 'iaf.response.submit', 'label' => 'ردُّ الإدارةِ على ملاحظة',
    'rule' => 'فصلُ الواجبات: الردُّ فعلُ الإدارةِ المُلاحَظِ عليها لا فعلُ المراجع',
    'fields' => array('finding_no' => 'رقمُ الملاحظة', 'response_text' => 'نصُّ الرد'),
    'call' => 'submitResponse',
    'args' => "'finding_no' => (string) (\$in['finding_no'] ?? ''), 'response_text' => (string) (\$in['response_text'] ?? ''),\n"
            . "                    'actor' => \$uid",
)),
'iaf_action_plans' => array(array(
    'key' => 'set', 'code' => 'iaf.actionplan.set', 'label' => 'ضبطُ خطةِ المعالجةِ ومالكِها ومهلتِها',
    'rule' => 'IAF-0044: لا خطةَ معالجةٍ بلا ردِّ إدارةٍ سابق',
    'fields' => array('finding_no' => 'رقمُ الملاحظة', 'action_plan' => 'خطةُ المعالجة', 'action_owner' => 'مالكُ الإجراء', 'action_due' => 'المهلة'),
    'call' => 'setActionPlan',
    'args' => "'finding_no' => (string) (\$in['finding_no'] ?? ''), 'action_plan' => (string) (\$in['action_plan'] ?? ''),\n"
            . "                    'action_owner' => (string) (\$in['action_owner'] ?? ''), 'action_due' => (string) (\$in['action_due'] ?? '')",
)),
);

/* ─ إضافةُ فعلِ «رفعِ ملاحظة» إلى شاشةِ الملاحظاتِ القائمةِ (لها فعلان سلفًا) ─ */
$RAISE = array(
    'key' => 'raise', 'code' => 'iaf.finding.raise', 'label' => 'رفعُ ملاحظة',
    'rule' => 'IAF-0025: رفعُ الملاحظةِ للمراجعِ الداخليِّ حصرًا — ولا ملاحظةَ بلا مهمة',
    'fields' => array('engagement_no' => 'رقمُ المهمة', 'title' => 'عنوانُ الملاحظة', 'severity' => 'الخطورة', 'auditee_dept' => 'الإدارةُ المُلاحَظة'),
    'call' => 'raiseFinding',
    'args' => "'engagement_no' => (string) (\$in['engagement_no'] ?? ''), 'title' => (string) (\$in['title'] ?? ''),\n"
            . "                    'severity' => (string) (\$in['severity'] ?? ''), 'auditee_dept' => (string) (\$in['auditee_dept'] ?? ''),\n"
            . "                    'actor' => \$uid",
);

/** يبني نصَّ عقدِ فعلٍ واحدٍ بصيغةِ البيان. */
function iaf_action_src(array $a)
{
    $f = array();
    foreach ($a['fields'] as $k => $lbl) { $f[] = "'{$k}' => '{$lbl}'"; }
    return "        '" . $a['key'] . "' => array(\n"
         . "            'code'  => '" . $a['code'] . "',\n"
         . "            'label' => '" . $a['label'] . "',\n"
         . "            'rule'  => '" . str_replace("'", "\\'", $a['rule']) . "',\n"
         . "            'fields' => array(" . implode(', ', $f) . "),\n"
         . "            'run' => function (\$conn, \$co, \$uid, \$in) {\n"
         . "                require_once __DIR__ . '/../app/Services/Audit/InternalAuditService.php';\n"
         /* ◆ گوتشا التقطها المسحُ الشامل: النصُّ هنا يُكتب في البيان **بـvar_export**
              ثم يُنسخ حرفيًّا إلى الشاشة — فلا يمرُّ بطبقةِ هروبٍ ثانية. فالمكتوبُ
              هو المُخرَج: شرطةٌ مائلةٌ واحدةٌ لكلِّ فاصلِ نطاق. وكتابةُ أربعٍ
              (`\\\\`) أنتجت `\\App\\Services` في الملفِّ المولَّد فانكسر تركيبُه —
              و**اختبارُ السلسلةِ لم يمسكه** لأنه ينادي الخدمةَ لا الشاشة. */
         . "                return \\App\\Services\\Audit\\InternalAuditService::" . $a['call'] . "(\$conn, array(\n"
         . "                    'company_id' => \$co, " . $a['args'] . "));\n"
         . "            }),\n";
}

$src = (string) file_get_contents($MAN);
$added = 0; $skipped = array();

foreach ($ACTIONS as $screen => $acts) {
    // موضعُ صفِّ الشاشةِ في البيان
    $needle = "array('code' => '" . $screen . "', 'dir' => 'Audit'";
    $pos = strpos($src, $needle);
    if ($pos === false) { $skipped[] = $screen . ' (لا صفَّ في البيان)'; continue; }
    // نهايةُ الصف: أولُ «),\n\n» بعدَه
    $end = strpos($src, "),\n", $pos);
    if ($end === false) { $skipped[] = $screen . ' (تعذّر تحديدُ نهايةِ الصف)'; continue; }
    $chunk = substr($src, $pos, $end - $pos);
    if (strpos($chunk, 'actions_src') !== false) { $skipped[] = $screen . ' (يحمل عقودًا سلفًا)'; continue; }

    $body = '';
    foreach ($acts as $a) { $body .= iaf_action_src($a); }
    $ins = ",\n          'actions_src' => \"\\n    'actions'    => array(\\n\"\n"
         . "            . " . var_export($body, true) . "\n"
         . "            . \"    ),\\n\"";
    $src = substr($src, 0, $end) . $ins . substr($src, $end);
    $added++;
}

/* فعلُ «رفعِ ملاحظة» يُلحق بشاشةِ الملاحظاتِ القائمة */
if (strpos($src, "'iaf.finding.raise'") === false) {
    $anchor = "'accept_evidence' => array(\\n\"";
    $p = strpos($src, $anchor);
    if ($p !== false) {
        $raiseSrc = str_replace(array('\\\\', "\n"), array('\\\\\\\\', "\\n\"\n            . \""), iaf_action_src($RAISE));
        $src = substr($src, 0, $p) . str_replace("\n", '', '') . substr($src, $p);
        // إلحاقٌ نصيٌّ بسيط: يُدرَج قبلَ 'accept_evidence'
        $src = preg_replace('/(\. "        \x27accept_evidence\x27 => array\(\\\\n"\n)/u',
            '. ' . var_export(iaf_action_src($RAISE), true) . "\n" . '$1', $src, 1);
        $added++;
    }
}

echo ($apply ? 'حُقن' : 'سيُحقن') . ": {$added} شاشة\n";
foreach ($skipped as $s) { echo "  تخطٍّ: {$s}\n"; }
if (!$apply) { echo "\n(عرضٌ فقط — أضف --apply)\n"; exit(0); }

@copy($MAN, $MAN . '.bak_inj0013');
file_put_contents($MAN, $src);
$lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($MAN) . ' 2>&1');
if (strpos($lint, 'No syntax errors') === false) {
    @copy($MAN . '.bak_inj0013', $MAN);
    echo "✘ تركيبٌ مكسورٌ — أُعيد البيانُ كما كان:\n{$lint}\n";
    exit(1);
}
echo "✔ البيانُ سليمٌ تركيبيًّا — شغّلْ: php tools/u13_screens_build.php --apply\n";
