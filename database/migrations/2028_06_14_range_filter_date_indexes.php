<?php
/**
 * 2028_06_14 — فهارسُ أعمدةِ التاريخِ التي تُرشِّح عليها الشيفرةُ **بمدًى**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا جولةٌ ثانية**: جولةُ `2028_06_13` فهرست أعمدةَ **المساواةِ** الشائعةَ
 *   (`company_id` · `contract_id` …) فأسقطت المسحَ الكاملَ من 7 إلى 1 من 8.
 *   والباقيةُ كشفت النقصَ: استعلامُ ميزانِ المراجعةِ يُرشِّح على `posting_date`
 *   وهو **ليس في قائمةِ الجولةِ الأولى أصلًا**. وقِيسَ حيًّا:
 *     · ميزانُ **سنةٍ** (84٪ من الصفوف)  — يبقى مسحًا كاملًا، **وهذا صوابُه**؛
 *       لا فهرسَ ينفع حين يطابق الشرطُ أغلبَ الجدول.
 *     · ميزانُ **شهرٍ** (الاستعمالُ الواقعيّ) — 6,713 صفًّا ⇐ **7 صفوفٍ · 0.23 م.ث**.
 *
 * ◆ **والقائمةُ مُشتقَّةٌ من الشيفرةِ لا مُخترَعةٌ بالاسم**: مُسِح 1,293 ملفَّ
 *   إنتاجٍ عن نمطِ `col >=|<=|BETWEEN` و`DATE(col)`. من 53 عمودَ تاريخٍ غيرِ
 *   مفهرسٍ في جداولَ كبيرةٍ، **يُرشَّح على 26 فقط** — و**أُقصيَ 27** لا يمسّها
 *   مُرشِّحُ مدًى في أيِّ ملفّ. ⛔ لا يُفهرَس ما لا يُستعمَل: الفهرسُ يُبطئ
 *   الكتابةَ ويشغل مساحةً، فثمنُه بلا مقابلٍ خسارةٌ صافية.
 *
 * ◆ **والتنقيةُ الأربعُ نفسُها** من الجولةِ الأولى: ≥500 صفٍّ · العمودُ قائمٌ ·
 *   لا يغطّيه فهرسٌ كبادئةٍ يسرى · وليس الجدولُ سقالةَ حملة.
 *
 * ◆ **ووسمٌ مستقلٌّ `ix_rng_`** لا `ix_hot_`: حتّى لا يُسقِطَ تراجعُ إحدى
 *   الجولتَين فهارسَ الأخرى — فكلُّ جولةٍ تملك تراجعَها وحدَه.
 *
 * ◆ **ويُحدَّث الإحصاءُ بعدَ الإنشاء**: فهرسٌ بلا `ANALYZE` يترك المُحسِّنَ
 *   يختار بأرقامِ تمايزٍ قديمةٍ فيقع على خطّةٍ أسوأَ من قبلِ الفهرسة (قِيسَ:
 *   5.20 ← 4.29 م.ث بمجرَّدِ التحديث).
 *
 * التشغيل: php database/migrations/2028_06_14_range_filter_date_indexes.php
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
$MIN_ROWS = 500;

/* الستَّةُ والعشرون المُشتقَّةُ من مسحِ الشيفرة — وبينها `posting_date` الذي
   أثبت القياسُ أثرَه (مسحٌ كاملٌ ⇐ مدًى بسبعةِ صفوف). */
$RANGE_COLS = array(
    'posting_date', 'txn_date', 'due_date', 'entry_date', 'log_date', 'work_date', 'paid_date',
    'valid_from', 'valid_to', 'effective_from', 'effective_to',
    'occurred_at', 'due_at', 'expires_at', 'closed_at', 'resolved_at', 'approved_at',
    'response_due_at', 'escalated_at', 'converted_at', 'started_at', 'claimed_at',
    'next_attempt_at', 'next_retry_at', 'lock_expires_at', 'applied_at', 'at',
);

/* ⚠ **الإسقاطُ يسبق قراءةَ الخريطة**: المسبارُ اليدويُّ `ix_hot_posting_date`
   أثبت الفكرةَ خارجَ الهجراتِ فلا أصلَ له في الدفتر — يُستبدَل بفهرسِ هذه
   الجولةِ ليصيرَ له أصلٌ مسجَّل. ولو قُرئت خريطةُ البادئاتِ قبلَه لعُدَّ العمودُ
   «مُغطًّى» فتُخطّيَ، ثمَّ أُسقط غطاؤه — **فيبقى العمودُ بلا فهرسٍ البتّة**.
   وقد وقع ذلك فعلًا في التشغيلِ الأوّلِ وكشفه القياسُ بعدَه. */
@$conn->query('ALTER TABLE `fin_journal_entries` DROP INDEX `ix_hot_posting_date`');

/* البادئاتُ اليسرى القائمة — تُقرأ مرّةً، **بعدَ** الإسقاطِ أعلاه */
$lead = array();
$r = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND SEQ_IN_INDEX = 1");
while ($r && ($x = $r->fetch_assoc())) { $lead[$x['t']][$x['c']] = 1; }

/* أعمدةُ التاريخِ وحدَها — لا يُفهرَس نصٌّ باسمِ تاريخ */
$dateCol = array();
$r = $conn->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND DATA_TYPE IN ('date','datetime','timestamp')");
while ($r && ($x = $r->fetch_assoc())) { $dateCol[$x['t']][$x['c']] = 1; }

$tabs = array();
$r = $conn->query("SELECT TABLE_NAME t FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
while ($r && ($x = $r->fetch_row())) { $tabs[] = $x[0]; }

$touched = array();
$made = 0; $skipScaf = 0; $skipSmall = 0; $skipCovered = 0; $skipNotDate = 0; $failed = array();
foreach ($tabs as $t) {
    if (preg_match($SCAF, $t)) { $skipScaf++; continue; }
    $cr = @$conn->query('SELECT COUNT(*) c FROM `' . $t . '`');
    if (!$cr) { continue; }
    if ((int) $cr->fetch_row()[0] < $MIN_ROWS) { $skipSmall++; continue; }

    foreach ($RANGE_COLS as $c) {
        if (!isset($dateCol[$t][$c])) { $skipNotDate++; continue; }
        if (isset($lead[$t][$c]))     { $skipCovered++; continue; }
        $idx = 'ix_rng_' . substr(md5($t . '|' . $c), 0, 10);
        if ($conn->query('ALTER TABLE `' . $t . '` ADD INDEX `' . $idx . '` (`' . $c . '`)')) {
            $made++;
            $touched[$t] = 1;
            $lead[$t][$c] = 1;                 /* حتّى لا يُعاد في التشغيلِ نفسِه */
        } else {
            $failed[] = $t . '.' . $c . ' :: ' . substr($conn->error, 0, 60);
        }
    }
}
echo "  ✔ فهارسُ مدًى أُنشئت          : {$made}\n";
echo "  ◦ مُغطًّى بفهرسٍ قائم         : {$skipCovered}\n";
echo "  ◦ جداولُ سقالةٍ مستثناة       : {$skipScaf}\n";
echo "  ◦ جداولُ دونَ {$MIN_ROWS} صفٍّ مستثناة : {$skipSmall}\n";
if ($failed) {
    echo "  ⚠ تعذّر (" . count($failed) . "):\n";
    foreach (array_slice($failed, 0, 6) as $f) { echo "       · {$f}\n"; }
}

$an = 0;
foreach (array_keys($touched) as $t) {
    if (@$conn->query('ANALYZE TABLE `' . $t . '`')) {
        $an++;
        while ($conn->more_results() && $conn->next_result()) { /* ANALYZE يُرجِع مجموعةَ نتائج */ }
    }
}
echo "  ✔ إحصاءٌ حُدِّث لـ {$an} جدولًا\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
