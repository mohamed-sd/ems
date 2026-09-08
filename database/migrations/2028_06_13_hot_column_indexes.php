<?php
/**
 * 2028_06_13 — فهارسُ أعمدةِ الترشيحِ الشائعةِ في جداولِ الإنتاجِ الكبيرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس**: سبعةٌ من ثمانيةِ مساراتٍ شائعةٍ تُنفَّذ بـ**مسحِ الجدولِ
 *   كاملًا** — 208,753 صفًّا تُمسَح لثمانيةِ استعلامات، بـ85.4 م.ث.
 *   واليومَ محتمَلٌ لأنَّ البياناتِ تجريبيّةٌ صغيرة (121 عقدًا · 654 سجلَّ دوام)،
 *   ⛔ **والمسحُ الكاملُ خطّيّ**: 26 م.ث على 13 ألفِ سطرٍ تصير **2.6 ثانيةً على
 *   مليون**. فهذا عطبٌ **تُخفيه البياناتُ الصغيرةُ تمامًا** ويظهر يومَ تُدخَل
 *   البياناتُ الحقيقيّة — وتغييرُه حينَها أصعبُ بكثير.
 *
 * ◆ **والتنقيةُ صارمةٌ — لا يُفهرَس إلّا ما استوفى أربعةً**:
 *   ① الجدولُ فيه **≥500 صفٍّ** — فلا فائدةَ من فهرسِ جدولٍ فارغ.
 *   ② العمودُ **قائمٌ فعلًا** في المخطَّط.
 *   ③ **ولا يغطّيه فهرسٌ قائمٌ كبادئةٍ يسرى** (`SEQ_IN_INDEX = 1`) — فالمركَّبُ
 *      `(company_id, x)` يغني عن فهرسٍ مفردٍ على `company_id`.
 *   ④ **وليس الجدولُ سقالةَ حملةٍ** (`repair01_` · `govui_` · `uxw_` …) — فلا
 *      يُحسَّن ما يُنتظَر حذفُه.
 *
 * ⚠ **والفهرسُ ليس مجّانيًّا**: يُبطئ الكتابةَ قليلًا ويشغل مساحة. ولذلك
 *   اقتُصر على **أعمدةِ ترشيحٍ شائعةٍ اثنَي عشر** لا على كلِّ عمود.
 * ◆ **ولا يغيّر سلوكًا**: الفهرسُ يُسرِّع ولا يبدّل نتيجةً — فالمقارنةُ قبلَ
 *   وبعدُ يجب أن تُعطيَ **الأرقامَ نفسَها** بزمنٍ أقلّ.
 * ◆ **ومُعاوَدٌ**: `IF NOT EXISTS` غيرُ متاحٍ للفهارسِ في MariaDB 11 بثقة،
 *   فيُفحَص وجودُ الفهرسِ قبلَ إنشائه — وتشغيلٌ ثانٍ يجد صفرَ عملٍ ويخرج.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$SCAF = '~^(repair01_|rpr0[0-9]_|govui_|injfrd|injfix|injint|injexec|uxw_|uxui_|ctl[0-9]?_|cmp0[0-9]_|u1[0-9]_)~';
$HOT  = array('company_id','created_at','is_deleted','status','state','user_id',
              'project_id','contract_id','equipment_id','event_id','entry_id','role_id');
$MIN_ROWS = 500;

/* الأعمدةُ المفهرسةُ كبادئةٍ يسرى — تُقرأ مرّةً */
$lead = array();
$r = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND SEQ_IN_INDEX = 1");
while ($r && ($x = $r->fetch_assoc())) { $lead[$x['t']][$x['c']] = 1; }

/* الأعمدةُ القائمةُ لكلِّ جدول */
$colsOf = array();
$r = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()");
while ($r && ($x = $r->fetch_assoc())) { $colsOf[$x['t']][$x['c']] = 1; }

$tabs = array();
$r = $conn->query("SELECT TABLE_NAME t FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
while ($r && ($x = $r->fetch_row())) { $tabs[] = $x[0]; }

$touched = array();
$made = 0; $skipScaf = 0; $skipSmall = 0; $skipCovered = 0; $failed = array();
foreach ($tabs as $t) {
    if (preg_match($SCAF, $t)) { $skipScaf++; continue; }
    $cr = @$conn->query("SELECT COUNT(*) c FROM `" . $t . "`");
    if (!$cr) { continue; }
    $n = (int) $cr->fetch_row()[0];
    if ($n < $MIN_ROWS) { $skipSmall++; continue; }

    foreach ($HOT as $h) {
        if (!isset($colsOf[$t][$h])) { continue; }
        if (isset($lead[$t][$h])) { $skipCovered++; continue; }
        $idx = 'ix_hot_' . substr(md5($t . '|' . $h), 0, 10);
        $sql = 'ALTER TABLE `' . $t . '` ADD INDEX `' . $idx . '` (`' . $h . '`)';
        if ($conn->query($sql)) {
            $made++;
            $touched[$t] = 1;
            $lead[$t][$h] = 1;                 /* حتى لا يُعاد في التشغيلِ نفسِه */
        } else {
            $failed[] = $t . '.' . $h . ' :: ' . substr($conn->error, 0, 60);
        }
    }
}
echo "  ✔ فهارسُ أُنشئت              : {$made}\n";
echo "  ◦ مُغطًّى بفهرسٍ قائم         : {$skipCovered}\n";
echo "  ◦ جداولُ سقالةٍ مستثناة       : {$skipScaf}\n";
echo "  ◦ جداولُ دونَ {$MIN_ROWS} صفٍّ مستثناة : {$skipSmall}\n";
if ($failed) {
    echo "  ⚠ تعذّر (" . count($failed) . "):\n";
    foreach (array_slice($failed, 0, 6) as $f) { echo "       · {$f}\n"; }
}

/* ◆ **والفهرسُ بلا إحصاءٍ يُضلِّل**: المُحسِّنُ يختار بأرقامِ تمايزٍ محفوظة،
 *   فإن أُنشئ فهرسٌ ولم يُحدَّث إحصاؤه اختار خطّةً أسوأَ من قبلِ الفهرسة.
 *   قِيسَ حيًّا: 5.20 ← 4.29 م.ث لتسليماتِ الأحداثِ بمجرَّدِ التحديث. */
$an = 0;
foreach (array_keys($touched) as $t) {
    if (@$conn->query("ANALYZE TABLE `{$t}`")) {
        $an++;
        while ($conn->more_results() && $conn->next_result()) { /* ANALYZE يُرجِع مجموعةَ نتائج */ }
    }
}
echo "  ✔ إحصاءٌ حُدِّث لـ {$an} جدولًا
";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
