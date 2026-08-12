<?php
/**
 * 2027_03_17 — إزالةُ بنودِ التسعيرِ وقراءاتِ المؤشرِ **الملفَّقة**
 * ═══════════════════════════════════════════════════════════════════════════
 * بأمرِ المالك: «حذفُ الملفَّقِ وإضافةُ داتا غيرِه غيرِ ملفَّقة». وهذه الثمانيةُ
 * بقيَّةُ حملةِ الـ958 صفًّا — أُغفلت لأنَّ المِلءَ العامَّ كتبها **خامًا** فلم
 * تمرَّ بحارسٍ يفضحها.
 *
 * ── والتلفيقُ مُثبَتٌ بعلاماتٍ لا تحتمل التأويل، لا بظنٍّ ────────────────────
 * أربعُ قراءاتِ مؤشرٍ (#59 · #64 · #69 · #74):
 *   · `index_code` = `source_ref` = **رقمُ عقدٍ** (`CONT-00005`) — ورمزُ المؤشرِ
 *     مصدرٌ منشورٌ لا رقمُ عقدٍ، والمرجعُ مستندُ قرارٍ لا نسخةٌ من الرمز.
 *   · و`created_by` **NULL** — فلا مُسعِّرَ لها. وهو ما تردُّه الخدمةُ اليومَ
 *     بـ403 («تسعيرٌ بلا مُسعِّرٍ مُعرَّفٍ») بعد قرارِ المالك 2026-08-12.
 *   · وقيمتُها = `base_index` بندِها إلى الرقمِ العشريِّ نفسِه (798.5 · 1483.5 …)
 *     — أي أنَّ «القراءةَ» نسخةٌ من مرجعِها فالدلتا صفرٌ حتمًا: عددٌ بلا معنى.
 *
 * أربعةُ بنودِ تسعيرٍ (#21 · #26 · #31 · #36):
 *   · `contract_item_id` يشير إلى بنودِ عقدٍ **غيرِ موجودةٍ** (5 · 10 · 15 · 20)
 *     — وحارسُ `saveTerm():92-99` يردُّ ذلك نصًّا، فلم تمرَّ به.
 *   · و`threshold_percent` = `pass_through_percent` (5=5 · 8.75=8.75 …) — رقمان
 *     مختلفا المعنى تطابقا، وهي بصمةُ مولِّدٍ يحشو لا يد.
 *   · و`created_by` NULL كذلك.
 *
 * ◆ ولا يُحذف صفٌّ له تابعٌ: يُتحقَّق أنَّ صفرَ مراجعةٍ تستند إلى هذه البنودِ
 *   **قبل** الإزالة، وإن وُجدت تابعةٌ تُوقَف الهجرةُ وتُعلَن — لا تُجرَّ بالقيد.
 * ◆ وكلُّ حذفٍ يُفحَص مُرجَعُه: `config.php` يضبط mysqli على عدمِ الرمي، فالفشلُ
 *   يعود **صامتًا** — والعدُّ قبلَ وبعدَ هو الحكم.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$one = function ($sql) use ($db) {
    $r = $db->query($sql);
    return $r ? (int) $r->fetch_row()[0] : -1;
};
$ids = function ($sql) use ($db) {
    $out = array();
    $r = $db->query($sql);
    while ($r && ($x = $r->fetch_row())) { $out[] = (int) $x[0]; }
    return $out;
};

/* ── ① التعرُّفُ بالعلاماتِ الثلاثِ مجتمعةً ─────────────────────────────────── */
$READ_W = "created_by IS NULL AND index_code = source_ref AND index_code LIKE 'CONT-%'";
$TERM_W = "created_by IS NULL AND index_code LIKE 'CONT-%'
           AND NOT EXISTS (SELECT 1 FROM contractequipments e WHERE e.id = contract_item_id)";

$readIds = $ids("SELECT id FROM contract_price_index_readings WHERE {$READ_W}");
$termIds = $ids("SELECT id FROM contract_price_terms WHERE {$TERM_W}");
echo '── ① ملفَّقٌ مُتعرَّفٌ عليه: ' . count($readIds) . ' قراءةً · ' . count($termIds) . " بندَ تسعيرٍ\n";
if (!$readIds && !$termIds) { echo "── لا شيءَ يُزال — الهجرةُ عاطلة\n"; exit(0); }

/* ── ② لا يُحذف صفٌّ له تابعٌ ────────────────────────────────────────────────── */
if ($termIds) {
    $in = implode(',', $termIds);
    $dep = $one("SELECT COUNT(*) FROM contract_price_revisions WHERE term_id IN ({$in})");
    echo "── ② مراجعاتٌ تستند إلى هذه البنود: {$dep}\n";
    if ($dep !== 0) {
        fwrite(STDERR, "بنودٌ لها مراجعاتٌ تابعةٌ — تُوقَف الهجرةُ ولا تُجَرُّ بالقيد. "
                     . "افحص المراجعاتِ أولًا.\n");
        exit(1);
    }
}

/* ── ③ الإزالةُ بفحصِ المُرجَعِ والعدِّ قبلَ وبعد ───────────────────────────────── */
$purge = function ($table, $where, $expect) use ($db, $one) {
    $before = $one("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
    $ok = $db->query("DELETE FROM `{$table}` WHERE {$where}");
    if ($ok === false) {
        fwrite(STDERR, "حذفٌ فشل على {$table}: " . $db->error . "\n");
        return false;
    }
    $gone = $db->affected_rows;
    $after = $one("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
    $good = ($gone === $expect && $after === 0);
    printf("── ③ %-34s قبل %2d · حُذف %2d · باقٍ %2d  %s\n",
           $table, $before, $gone, $after, $good ? '✔' : '✘ خلافُ المتوقَّع');
    return $good;
};
$allOk = true;
$allOk = $purge('contract_price_index_readings', $READ_W, count($readIds)) && $allOk;
$allOk = $purge('contract_price_terms', $TERM_W, count($termIds)) && $allOk;

/* ── ④ ولا يُترك يتيمٌ: قراءةٌ برمزِ مؤشرٍ لا بندَ يستعمله ──────────────────── */
$orphan = $one("SELECT COUNT(*) FROM contract_price_index_readings r
                 WHERE NOT EXISTS (SELECT 1 FROM contract_price_terms t
                                    WHERE t.index_code = r.index_code)");
echo "── ④ قراءاتٌ برمزٍ لا بندَ يستعمله: {$orphan}"
   . ($orphan > 0 ? "  (تُعلَن ولا تُحذف — قد تُسبِق بندَها)\n" : "\n");

$leftR = $one('SELECT COUNT(*) FROM contract_price_index_readings');
$leftT = $one('SELECT COUNT(*) FROM contract_price_terms');
echo "── ⑤ الباقي: {$leftR} قراءةً · {$leftT} بندَ تسعيرٍ\n";
if (!$allOk) { fwrite(STDERR, "لم تكتمل الإزالةُ كما تُتوقَّع\n"); exit(1); }

echo "\n✅ أُزيل الملفَّقُ بعلاماتِه لا بظنٍّ — والبديلُ يدخل من بابِ الخدمةِ في بذرةِ uat0002.\n";
exit(0);
