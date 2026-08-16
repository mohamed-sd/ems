<?php
/**
 * 2027_04_21_unit_entries_drop_redundant_hours.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تراجعٌ عن سبعةِ أعمدةٍ أضافتها 2027_04_19 — لها نظيرٌ حيٌّ أغنى منها
 *
 * ── الخطأ ────────────────────────────────────────────────────────────────
 * أضافت الهجرةُ السابقةُ `run_hours` و`standby_hours` و`breakdown_hours`
 * و`stop_reason_code` و`liable_party` و`tons` و`meters` إلى `unit_entries`
 * نقلًا عن المواصفة 70، بعد أن قِيست نظائرُها في `timesheet` وحدَه. ولم يُفحص
 * `unit_time_log` — وفيه النموذجُ قائمٌ **مطبَّعًا** منذ 2026:
 *
 *   ops_state ENUM('actual_work','standby','tech_breakdown','supplier_stop',
 *                  'operator_stop','client_stop','fuel_logistics_stop',
 *                  'planned_stop','force_majeure','unlogged')
 *   resp_party · cause_note · hours · entry_id → unit_entries.id
 *
 * القياسُ الحيّ: 12,500 سطرًا · 81,758 ساعة · 11,346 قيدًا · 1.10 سطرٍ للقيد.
 *
 * ── ولماذا المطبَّعُ أصدقُ من المسطَّح ──────────────────────────────────
 * العمودُ المسطَّحُ `breakdown_hours` رقمٌ واحدٌ لسببٍ واحد. والمطبَّعُ يحمل
 * **سبعةَ أسبابِ توقفٍ متمايزة** ويسمح بأكثرَ من سببٍ في الورديةِ الواحدةِ
 * لكلٍّ ساعاتُه وطرفُه المسؤول — وهو ما لا يعبّر عنه عمودٌ مسطَّحٌ البتة.
 * و`tons`/`meters` نظيرُهما `qty` + `unit_type` ENUM(hour,ton,meter,…).
 *
 * فإبقاءُ السبعةِ ينشئ **مصدرَ حقيقةٍ ثانيًا للساعاتِ نفسِها** — وهو بعينُه
 * العيبُ الذي رصدته المراجعةُ العكسيةُ في هذا النظامِ مرتين. والقاعدةُ التي
 * مُنعنا بها من إنشاءِ جدولٍ لمفهومٍ له نظير تسري على العمودِ سواءً بسواء.
 *
 * ── وآمنٌ بلا فقدِ بيان ──────────────────────────────────────────────────
 * السبعةُ أُضيفت في الجولةِ نفسِها ولم تُكتب قط: قِيست قبلَ الحذفِ فكانت
 * **صفرَ صفٍّ غيرِ فارغٍ في كلٍّ منها**. والساعاتُ تبقى حيث هي في `unit_time_log`.
 * ويُسقط معها قيدا CHECK اللذان يشيران إليها؛ ويبقى `chk_ue_meter` لأن
 * `meter_before`/`meter_after` لا نظيرَ لهما في المخطط.
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

echo "══ تراجعٌ: الساعاتُ مكانُها unit_time_log لا أعمدةٌ مسطَّحة ══\n\n";

$hasCol = function (string $c) use ($conn): bool {
    $r = $conn->query("SHOW COLUMNS FROM `unit_entries` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$hasChk = function (string $c) use ($conn): bool {
    $r = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='unit_entries'
          AND CONSTRAINT_NAME='" . $conn->real_escape_string($c) . "' AND CONSTRAINT_TYPE='CHECK'");
    return $r && $r->num_rows > 0;
};

/* ── ① القيدانِ المشيرانِ إلى الأعمدةِ المحذوفة ─────────────────────── */
foreach (['chk_ue_hours', 'chk_ue_liable'] as $chk) {
    if (!$hasChk($chk)) { echo "  · $chk غيرُ موجود\n"; continue; }
    if ($conn->query("ALTER TABLE unit_entries DROP CONSTRAINT `$chk`")) { echo "  ✔ أُسقط القيد $chk\n"; }
    else { exit("  ✘ تعذّر إسقاطُ $chk: {$conn->error}\n"); }
}

/* ── ② السبعةُ — ولا تُحذف إلا بعد إثباتِ خلوِّها صفًّا صفًّا ────────── */
$DROP = [
    'run_hours'        => "unit_time_log.ops_state='actual_work'",
    'standby_hours'    => "unit_time_log.ops_state='standby'",
    'breakdown_hours'  => "unit_time_log.ops_state IN ('tech_breakdown','supplier_stop','operator_stop','client_stop','fuel_logistics_stop','planned_stop','force_majeure')",
    'stop_reason_code' => 'unit_time_log.ops_state + cause_note',
    'liable_party'     => 'unit_time_log.resp_party',
    'tons'             => "unit_entries.qty حيث unit_type='ton'",
    'meters'           => "unit_entries.qty حيث unit_type='meter'",
];
$dropped = 0;
foreach ($DROP as $c => $where) {
    if (!$hasCol($c)) { echo "  · $c غيرُ موجودٍ سلفًا\n"; continue; }
    $r = $conn->query("SELECT COUNT(*) FROM unit_entries WHERE `$c` IS NOT NULL AND `$c` <> 0 AND `$c` <> ''");
    $filled = $r ? (int) $r->fetch_row()[0] : -1;
    if ($filled !== 0) {
        echo "  ⚠ $c فيه $filled صفًّا مملوءًا — لم يُحذف. يلزم ترحيلُ قيمِه إلى نظيرِه أولًا\n";
        continue;
    }
    if ($conn->query("ALTER TABLE unit_entries DROP COLUMN `$c`")) {
        echo "  ✔ حُذف $c (صفرُ مملوء) ⇐ نظيرُه: $where\n"; $dropped++;
    } else { echo "  ✘ $c: {$conn->error}\n"; }
}

/* ── ③ إثباتُ أنَّ الساعاتِ لم تُمسّ ──────────────────────────────── */
echo "\n── الساعاتُ في موضعِها ──\n";
$r = $conn->query("SELECT COUNT(*) n, ROUND(SUM(hours),1) h, COUNT(DISTINCT entry_id) e FROM unit_time_log");
if ($r) { $x = $r->fetch_row(); echo "  unit_time_log: {$x[0]} سطرًا · {$x[1]} ساعة · {$x[2]} قيدًا — لم تُمسّ\n"; }
$r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unit_entries'");
echo '  أعمدةُ unit_entries الآن: ' . ($r ? $r->fetch_row()[0] : '?') . " (كانت 69 بعد 04_19 و53 قبلَها)\n";
echo "  أُبقي: entity_layer · container_key · client_id · meter_before · meter_after · fuel_received_qty · fuel_issued_qty · created_by_role · seed_tag (تسعةٌ لا نظيرَ لها)\n";
echo "  حُذف: $dropped\n";
echo "\n✔ تمّت\n";
