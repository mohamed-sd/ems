<?php
/**
 * 2028_03_13_govui_iaf_field_closure.php — إغلاقُ حقولِ إدارةِ المراجعةِ الداخليّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المسألة**: تسعةُ أسطحٍ مولَّدةٍ بعُدّةِ `u13` في `Audit/` تقرأ أعمدتَها من
 *   `gov_field_class` (عقدُ `u13_columns()`: عمودٌ موجودٌ + مصنَّفٌ + غيرُ تقنيّ).
 *   وقيدُها كُتب بمفرداتِ **التنفيذِ** لا بمفرداتِ **الحاكم** — فـ`finding_no`
 *   مُسمًّى «رقمُ الملاحظة» والدليلُ يسمّيه «معرّف الملاحظة»؛ وحقولُ الملاحظةِ
 *   الرقابيّةِ المعياريّةُ (المعيار · الواقع · السبب الجذري · التوصية) **لا
 *   عمودَ لها أصلًا**.
 *
 * ◆ **والعقدُ في `_iaf_field_closure_spec.php`** يقرؤه الأمامُ والعكسُ معًا —
 *   فلا قائمتان تنجرفان، ولا رجوعَ مرهونٌ ببقاءِ سجلٍّ قد يُسقَط.
 *
 * ◆ **وبوّابةُ المستأجرِ (ADR-02) أسقطت سجلَّ هذه الدفعةِ في أوّلِ تشغيلٍ**
 *   لأنّه بلا `company_id` — فبقيت 77 عمودًا و41 وسمًا بلا طريقِ رجوع.
 *   فالسجلُّ الآن **بـ`company_id`**، وللهجرةِ **مسارُ ترميمٍ** يكتب الأثرَ
 *   الغائبَ. ⛔ **ولا يُطلَق الترميمُ إلّا بشرطِه**: الحالةُ مطابقةٌ للعقدِ
 *   بتمامِها **والسجلُّ خالٍ** — فعلى قاعدةٍ بكرٍ لا يُطلَق (الأعمدةُ غائبة)،
 *   فلا يُسجَّل عمودٌ لم تُنشئْه هذه الدفعة.
 *
 * @migration-objects: alter iaf_charter, iaf_universe, iaf_plan, iaf_independence,
 *   iaf_engagements, iaf_workpapers, exec_audit_reports, iaf_quality_reviews,
 *   iaf_findings; seed gov_field_class; create govui_field_closure_log
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* ◆ `company_id` شرطُ بوّابةِ المستأجر لأيِّ جدولٍ جديد (ADR-02) —
     والسجلُّ أثرُ تشغيلٍ فيلزمه الانتماءُ كغيرِه. */
$conn->query("CREATE TABLE IF NOT EXISTS govui_field_closure_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT NOT NULL DEFAULT 1,
    batch VARCHAR(40) NOT NULL,
    action VARCHAR(16) NOT NULL,
    screen_code VARCHAR(64) NOT NULL,
    tbl VARCHAR(64) NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    old_label VARCHAR(255) NULL,
    new_label VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY k_batch (batch), KEY k_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$BATCH = 'IAF_FIELD_CLOSURE';
$SPEC  = require __DIR__ . '/_iaf_field_closure_spec.php';

$logSt = $conn->prepare("INSERT INTO govui_field_closure_log
    (company_id, batch, action, screen_code, tbl, field_key, old_label, new_label)
    VALUES (1,?,?,?,?,?,?,?)");
$logged = function ($act, $screen, $tbl, $key, $old, $new) use ($logSt, $BATCH) {
    $logSt->bind_param('sssssss', $BATCH, $act, $screen, $tbl, $key, $old, $new);
    $logSt->execute();
};

/* حالةُ الجداولِ والقيودِ الراهنة */
$colsOf = array(); $clsOf = array();
foreach ($SPEC as $screen => $s) {
    $colsOf[$screen] = array();
    $q = $conn->query("SHOW COLUMNS FROM `{$s['table']}`");
    while ($q && ($x = $q->fetch_assoc())) { $colsOf[$screen][$x['Field']] = true; }
    $clsOf[$screen] = array();
    $st = $conn->prepare("SELECT field_key, label_ar FROM gov_field_class WHERE screen_code = ?");
    $st->bind_param('s', $screen); $st->execute(); $rs = $st->get_result();
    while ($x = $rs->fetch_assoc()) { $clsOf[$screen][$x['field_key']] = $x['label_ar']; }
    $st->close();
}

/* ─── هل الحالةُ مطابقةٌ للعقدِ بتمامِها؟ (شرطُ الترميم) ─────────────────── */
$allDone = true;
foreach ($SPEC as $screen => $s) {
    foreach ($s['rename'] as $k => $r) {
        if (!isset($clsOf[$screen][$k]) || $clsOf[$screen][$k] !== $r[0]) { $allDone = false; break 2; }
    }
    foreach ($s['add'] as $a) {
        if (!isset($colsOf[$screen][$a[0]])) { $allDone = false; break 2; }
        if (!isset($clsOf[$screen][$a[0]]) || $clsOf[$screen][$a[0]] !== $a[2]) { $allDone = false; break 2; }
    }
}
$have = 0;
$q = $conn->query("SELECT COUNT(*) FROM govui_field_closure_log WHERE batch = '{$BATCH}'");
if ($q) { $have = (int) $q->fetch_row()[0]; }

if ($allDone && $have === 0) {
    /* ◆ ترميمُ الأثرِ الضائع — الحالةُ مكتوبةٌ والسجلُّ خالٍ، فيُكتب ما جرى. */
    $n = 0;
    foreach ($SPEC as $screen => $s) {
        foreach ($s['add'] as $a) {
            $logged('add_col',  $screen, $s['table'], $a[0], null, $a[2]);
            $logged('register', $screen, $s['table'], $a[0], null, $a[2]); $n += 2;
        }
        foreach ($s['rename'] as $k => $r) {
            $logged('rename', $screen, $s['table'], $k, $r[1], $r[0]); $n++;
        }
    }
    $logSt->close();
    echo "ترميمُ أثرٍ: الحالةُ مطابقةٌ للعقدِ والسجلُّ كان خاليًا — قُيِّد {$n} فعلًا.\n";
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
    return;
}

/* ─── التنفيذُ المعتاد ─────────────────────────────────────────────────── */
$nAdd = 0; $nRen = 0; $nReg = 0; $skip = array();

foreach ($SPEC as $screen => $s) {
    $tbl = $s['table'];

    /* ① إعادةُ التسمية — ولا تُدهَس إلّا مفردةُ التنفيذِ المعلومة */
    foreach ($s['rename'] as $key => $r) {
        list($new, $was) = $r;
        if (!isset($clsOf[$screen][$key])) { $skip[] = "{$screen}.{$key}: لا قيدَ لإعادةِ تسميتِه"; continue; }
        $cur = $clsOf[$screen][$key];
        if ($cur === $new) { continue; }
        if ($cur !== $was) { $skip[] = "{$screen}.{$key}: الوسمُ «{$cur}» ليس المفردةَ المتوقَّعةَ «{$was}» — لم يُمَسّ"; continue; }
        $up = $conn->prepare("UPDATE gov_field_class SET label_ar = ? WHERE screen_code = ? AND field_key = ?");
        $up->bind_param('sss', $new, $screen, $key); $up->execute(); $up->close();
        $logged('rename', $screen, $tbl, $key, $cur, $new); $nRen++;
    }

    /* ② الأعمدةُ الجديدةُ ثمَّ قيدُها */
    foreach ($s['add'] as $a) {
        list($key, $type, $label, $dc) = $a;
        if (!isset($colsOf[$screen][$key])) {
            if (!$conn->query("ALTER TABLE `{$tbl}` ADD COLUMN `{$key}` {$type} NULL")) {
                $skip[] = "{$tbl}.{$key}: " . $conn->error; continue;
            }
            $logged('add_col', $screen, $tbl, $key, null, $label); $nAdd++;
        }
        if (isset($clsOf[$screen][$key])) {
            if ($clsOf[$screen][$key] !== $label) {
                $up = $conn->prepare("UPDATE gov_field_class SET label_ar = ?, active = 1
                                       WHERE screen_code = ? AND field_key = ?");
                $up->bind_param('sss', $label, $screen, $key); $up->execute(); $up->close();
                $logged('rename', $screen, $tbl, $key, $clsOf[$screen][$key], $label); $nRen++;
            }
            continue;
        }
        $ins = $conn->prepare("INSERT INTO gov_field_class
            (company_id, screen_code, field_key, label_ar, dc_code, is_sensitive, active)
            VALUES (1, ?, ?, ?, ?, 0, 1)");
        $ins->bind_param('ssss', $screen, $key, $label, $dc); $ins->execute(); $ins->close();
        $logged('register', $screen, $tbl, $key, null, $label); $nReg++;
    }
}
$logSt->close();

printf("أعمدة مضافة %d · قيود مسجلة %d · أسماء مصححة %d\n", $nAdd, $nReg, $nRen);
if ($skip) { echo "متجاوَز:\n"; foreach ($skip as $x) { echo "  - {$x}\n"; } }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
