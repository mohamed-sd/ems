<?php
/**
 * 2027_04_11_exec_gov_spine_columns.php
 * ═══════════════════════════════════════════════════════════════════════════
 * عمودُ الحوكمةِ يصير حقيقةً مخزَّنةً — ⇐ INJ-0416 · INJ-0417 · INJ-0418 · INJ-0419
 *
 * نصُّ القبولِ في الأربعةِ واحدٌ: «عددُ أعمدة الجدول في الشاشة = N **ويطابق
 * أسماءَ الوثيقة عمودًا عمودًا**».
 *
 * ── ما هو الفرقُ الذي تعلنه الوثيقةُ ─────────────────────────────────────
 * قِيس على **٣٥ شاشةً** مربوطةً بالوثيقة، فبان أنَّ فرقَ العدِّ ليس اعتباطًا:
 * **في ٢٢ منها يساوي الفرقُ بالضبط عددَ الأعمدةِ الحاكمةِ الناقصةِ من السبعة**
 * (الكيان · مرجع التفويض · تاريخ الاعتماد · تاريخ الإنشاء · المرجع الأب ·
 * المُنشئ — الاسم والصفة · المعتمِد — الاسم والصفة). والثلاثةَ عشرَ الباقيةُ
 * تحمل بعضَها **بمرادفٍ** (كـ«تاريخ الرفع» بدل «تاريخ الإنشاء») فلا يلتقطه
 * المطابِقُ الحرفيُّ — لا أنَّ القاعدةَ تخيب.
 *
 * فالفرقُ إذًا **عمودُ حوكمةٍ ناقصٌ**، لا عمودُ عملٍ منسيّ.
 *
 * ── والعمودُ لا يُعلَن إلا وله مصدرٌ مخزَّن ───────────────────────────────
 * تسعةٌ من الخمسةَ عشرَ المطلوبةِ **موجودةٌ في الجداولِ سلفًا** (`created_at` ·
 * `created_by_name` · `source_request_id` · `contract_id` · `contract_ref` ·
 * `authority_ref` …) — ينقصها الإعلانُ في الشاشةِ لا التخزين. وسبعةٌ تحتاج
 * عمودًا حقيقيًّا، وهي التي تضيفها هذه الهجرة.
 *
 * ◆ **ولا تُخترع بيانةٌ**: يُردَم ما هو **مسجَّلٌ فعلًا** بصيغةٍ أخرى
 *   (تاريخُ الاعتماد من تاريخِ القرارِ حين كان القرارُ اعتمادًا · والمعتمِدُ من
 *   المُوقِّعِ عنّا) — وما لا سجلَّ له يبقى NULL ويُعرض «—»، فـ«لا يُعرف» صدقٌ
 *   و«صفرٌ مُختلَق» كذب.
 * ◆ وعاطلةٌ: تُفحص كلُّ إضافةٍ بـ`SHOW COLUMNS` قبلها، ويُفحص مُرجَعُ كلِّ جملة.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ عمودُ الحوكمةِ يصير حقيقةً مخزَّنةً ══\n\n";

$hasCol = function ($t, $c) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

/* جدول · عمود · تعريف · ماذا يحمل */
$ADD = array(
    array('exec_approvals', 'approved_at', 'DATETIME NULL', 'لحظةُ الاعتماد — وبها يُقاس زمنُ الدورة'),
    array('exec_contract_signings', 'approver_name', 'VARCHAR(120) NULL', 'من اعتمده وبأي صفة'),
    array('exec_contract_signings', 'approver_authority_ref', 'VARCHAR(120) NULL', 'سندُ صلاحيةِ المعتمِد — غيرُ سندِ المُوقِّع'),
    array('exec_contract_signings', 'approved_at', 'DATETIME NULL', 'لحظةُ الاعتماد'),
    array('exec_project_charters', 'authority_ref', 'VARCHAR(120) NULL', 'سندُ صلاحيةِ معتمِدِ القرار'),
    array('exec_decisions', 'authority_ref', 'VARCHAR(120) NULL', 'سندُ صلاحيةِ معتمِدِ القرار'),
    array('exec_decisions', 'parent_ref', 'VARCHAR(64) NULL', 'المستندُ الذي تولَّد عنه — خيطُ التتبع'),
);
$added = 0; $there = 0;
foreach ($ADD as $a) {
    list($t, $c, $def, $why) = $a;
    if ($hasCol($t, $c)) { $there++; echo "  · {$t}.{$c} قائمٌ سلفًا\n"; continue; }
    if (!$conn->query("ALTER TABLE `{$t}` ADD COLUMN `{$c}` {$def} COMMENT '"
            . $conn->real_escape_string($why) . "'")) {
        echo "  ✘ {$t}.{$c}: {$conn->error}\n";
        continue;
    }
    $added++;
    echo "  ✔ {$t}.{$c} — {$why}\n";
}
echo "\n  أُضيف: {$added} · قائمٌ: {$there}\n";

/* ── الردمُ من المسجَّلِ لا من الخيال ───────────────────────────────────── */
echo "\n── ردمُ ما هو مسجَّلٌ فعلًا بصيغةٍ أخرى\n";
$fill = function ($sql, $label) use ($conn) {
    if (!$conn->query($sql)) { echo "  ✘ {$label}: {$conn->error}\n"; return; }
    echo '  ✔ ' . $label . ' — ' . $conn->affected_rows . " صفًّا\n";
};
/* القرارُ الذي كان اعتمادًا: تاريخُه هو تاريخُ الاعتماد */
$fill("UPDATE exec_approvals SET approved_at = decision_date
        WHERE approved_at IS NULL AND decision_date IS NOT NULL AND decision LIKE 'اعتماد%'",
    'exec_approvals.approved_at ← تاريخُ القرارِ حين كان اعتمادًا');
/* المُوقِّعُ عنّا هو المعتمِدُ الداخليُّ المسجَّل، وتاريخُ التوقيعِ لحظةُ الاعتماد */
$fill("UPDATE exec_contract_signings SET approver_name = signed_by_us
        WHERE approver_name IS NULL AND signed_by_us IS NOT NULL AND signed_by_us <> ''",
    'exec_contract_signings.approver_name ← المُوقِّعُ عنّا');
$fill("UPDATE exec_contract_signings SET approved_at = signing_date
        WHERE approved_at IS NULL AND signing_date IS NOT NULL",
    'exec_contract_signings.approved_at ← تاريخُ التوقيع');

echo "\n✔ تمّت\n";
