<?php
/**
 * 2028_02_22_nav001_retired_terms_sweep.php — كنسُ اللفظِ المتقاعدِ وفعلِ المتكلِّمِ من `link_groups`
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:link_groups(name,stage_title) + log:gov_cycle_name_log
 *
 * ◆ **الحاجزُ الذي يرفعه**: `tests/injfrd01_nav001_source_clean.php` يرسّب على
 *   شقَّين مقيسَين بأسمائِهما:
 *   ① **لفظٌ متقاعدٌ بالجذرِ** في الجدولِ الذي يُرسَم منه — خمسةُ صفوف.
 *   ② **فعلُ متكلِّمٍ مُصيَّرٌ** — «نقرأ تقارير إدارتي» في أربعةَ عشرَ صفًّا (اثنان حيّان).
 *
 * ◆ **والبدائلُ منقولةٌ من مصدرِها لا مخترعة**:
 *   · «توزيع الحصص والحاويات» ⇐ «توزيع الحصص وإسناد المعدات» — **بنصِّ
 *     `gov_cycle_name_log` نفسِه** (قيدٌ سابقٌ نُفِّذ في مواضعَ ولم يبلغْ هذه).
 *   · «نوزّع الحاويات» ⇐ «إسناد المعدات للوحدات» — بنصِّ السجلِّ نفسِه.
 *   · «حصص الموردين وحاوياتها» ⇐ «حصص الموردين والوحدات التعاقدية» — وهو
 *     **اسمُ السطحِ في ورقةِ الموردين حرفًا** (`02 · الدليل` — السطح 12).
 *   · «نقرأ تقارير إدارتي» ⇐ «تقارير إدارتي» — نزعُ الفعلِ وحدَه، والاسمُ باقٍ.
 *   ⛔ ولا بديلَ من عندي: كلُّ واحدٍ له مصدرٌ مسمًّى في تعليقِه.
 *
 * ◆ **وكلُّ تغييرٍ يُقيَّد بقيمتِه السابقة** في `gov_cycle_name_log` — فالرجوعُ
 *   ممكنٌ والفحصُ ⑧ في الشاهدِ نفسِه يتحقّق أنَّ النمطَ ما يزال يمسك ما في السجل.
 *
 * التشغيل: php database/migrations/2028_02_22_nav001_retired_terms_sweep.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

/* field => array(old => new) */
$MAP = array(
    'name' => array(
        'حصص الموردين وحاوياتها' => 'حصص الموردين والوحدات التعاقدية',
        'توزيع الحصص والحاويات'  => 'توزيع الحصص وإسناد المعدات',
        'نقرأ تقارير إدارتي'      => 'تقارير إدارتي',
    ),
    'stage_title' => array(
        'رابعا نوزع الحاويات'  => 'رابعا إسناد المعدات للوحدات',
        'رابعًا نوزّع الحاويات' => 'رابعًا إسناد المعدات للوحدات',
        'نقرأ تقارير إدارتي'    => 'تقارير إدارتي',
    ),
);
$log = $conn->prepare("INSERT INTO gov_cycle_name_log (row_id, field, old_value, new_value, requirement_id, changed_at)
                       VALUES (?,?,?,?, 'FR-NAV-001', NOW())");
if (!$log) { exit("⛔ prepare log: {$conn->error}\n"); }
$n = 0;
foreach ($MAP as $field => $pairs) {
    foreach ($pairs as $old => $new) {
        $sel = $conn->prepare("SELECT id FROM link_groups WHERE `{$field}` = ?");
        $sel->bind_param('s', $old);
        $sel->execute();
        $rs = $sel->get_result();
        $ids = array();
        while ($x = $rs->fetch_row()) { $ids[] = (int) $x[0]; }
        $sel->close();
        if (!$ids) { continue; }
        $up = $conn->prepare("UPDATE link_groups SET `{$field}` = ? WHERE id = ?");
        foreach ($ids as $gid) {
            $up->bind_param('si', $new, $gid);
            if (!$up->execute()) { exit("⛔ update {$gid}: {$conn->error}\n"); }
            $log->bind_param('isss', $gid, $field, $old, $new);
            if (!$log->execute()) { exit("⛔ log {$gid}: {$conn->error}\n"); }
            $n++;
        }
        $up->close();
        echo "  ✔ {$field}: «{$old}» ⇐ «{$new}» × " . count($ids) . "\n";
    }
}
$log->close();
echo "✔ صفوفٌ صُحِّحت ومقيَّدةٌ بقيمتِها السابقة: {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
