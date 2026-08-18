<?php
/**
 * 2027_07_05_uat_note_pollution.php — تلوّثُ «نصِّ الملاحظةِ في خانةِ الرمز»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العائلةُ نفسُها التي كشفتها المرحلةُ (ج) في `approval_workflow_rules`:
 *   نصُّ ملاحظةٍ بشريةٍ («وفق المعتمد في محضر الإدارة · UAT-2026») مكتوبٌ في
 *   **خانةِ رمزٍ أو نوعٍ** — فيُعرض للمستخدمِ نوعًا للصيانةِ وأولويةً ومصدرًا.
 *   وبوابةُ G16 (ف١٣-٣) ترسّب ظهورَ بياناتِ الاختبارِ في واجهةٍ نهائية.
 * ◆ والخانةُ المسمومةُ ليست الصفَّ: أمرُ الصيانةِ عملٌ حقيقيٌّ بمعدتِه وتكلفتِه —
 *   فلا يُحذف الصفُّ ولا يُخفى. يُنظَّف **الحقلُ المسمومُ وحدَه** ويُحفظ نصُّه
 *   الأصليُّ في حجرٍ ثلاثين يومًا (قاعدةُ المالكِ الرابعة: لا حذفَ مدمِّر).
 * ◆ والنصُّ المنقولُ لا يضيع: يُلحق بحقلِ الملاحظاتِ إن وُجد — فهو ملاحظةٌ
 *   كُتبت في غيرِ موضعِها، وإعادتُها لموضعِها إصلاحٌ لا حذف (صفرُ فقد).
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* ── حجرُ القيمِ الملوَّثة ── */
$conn->query("CREATE TABLE IF NOT EXISTS `uat_field_quarantine` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `table_name` VARCHAR(64) NOT NULL,
  `row_id` INT NOT NULL,
  `column_name` VARCHAR(64) NOT NULL,
  `polluted_value` TEXT NOT NULL COMMENT 'النصُّ كما كان — يُستعاد بأمرٍ واحد',
  `moved_to_notes` TINYINT(1) NOT NULL DEFAULT 0,
  `quarantined_at` DATETIME NOT NULL,
  `purge_after` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_tbl_row` (`table_name`, `row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='حجرُ قيمِ حقولٍ ملوَّثةٍ بنصِّ ملاحظةٍ/UAT — ثلاثون يومًا ثم حذف'");

/* الحقولُ المرشَّحةُ: خاناتُ رمزٍ/نوعٍ/أولويةٍ لا تحتمل جملةً بشرية */
$TARGETS = array(
    'mnt_order'     => array('maint_type', 'source', 'priority', 'cost_party'),
    /* السببُ الجذريُّ حقلٌ نصيٌّ مشروع — لكنَّ قيمَه كلَّها نصُّ UAT لا سببًا
       (قيس: 17 صفًّا · كلُّ قيمةٍ من عائلةِ «وفق المعتمد… · UAT-2026»). */
    'risk_register' => array('root_cause'),
);
$purge = date('Y-m-d', strtotime('+30 days'));
$MARK = 'UAT-2026';
$total = 0; $moved = 0;

foreach ($TARGETS as $tbl => $cols) {
    /* هل للجدولِ حقلُ ملاحظاتٍ يُنقل إليه النصّ؟ */
    $noteCol = null;
    foreach (array('notes', 'note', 'remarks', 'description', 'memo') as $c) {
        $q = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                             AND TABLE_NAME='{$tbl}' AND COLUMN_NAME='{$c}'");
        if ($q && $q->num_rows > 0) { $noteCol = $c; break; }
    }
    foreach ($cols as $col) {
        $q = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                             AND TABLE_NAME='{$tbl}' AND COLUMN_NAME='{$col}'");
        if (!$q || $q->num_rows === 0) { continue; }
        $rows = $conn->query("SELECT id, `{$col}` v FROM `{$tbl}` WHERE `{$col}` LIKE '%{$MARK}%'");
        while ($rows && ($x = $rows->fetch_assoc())) {
            $id = (int) $x['id']; $val = (string) $x['v'];
            $ins = $conn->prepare("INSERT INTO uat_field_quarantine (table_name,row_id,column_name,polluted_value,moved_to_notes,quarantined_at,purge_after)
                                   VALUES (?,?,?,?,?,NOW(),?)");
            $didMove = 0;
            /* النصُّ يعود لموضعِه: يُلحق بالملاحظاتِ إن وُجد حقلُها */
            if ($noteCol !== null) {
                $clean = trim(str_replace('·', '', str_replace($MARK, '', $val)));
                if ($clean !== '') {
                    $up = $conn->prepare("UPDATE `{$tbl}` SET `{$noteCol}` = TRIM(CONCAT(COALESCE(`{$noteCol}`,''), ' | ', ?)) WHERE id = ?");
                    $up->bind_param('si', $clean, $id);
                    if ($up->execute() && $up->affected_rows > 0) { $didMove = 1; $moved++; }
                }
            }
            $ins->bind_param('sissis', $tbl, $id, $col, $val, $didMove, $purge);
            $ins->execute();
            /* ثم تُفرَّغ الخانةُ المسمومةُ — القيمةُ محفوظةٌ في الحجرِ والنصُّ في الملاحظات */
            $conn->query("UPDATE `{$tbl}` SET `{$col}` = NULL WHERE id = {$id}");
            $total++;
        }
    }
}

/* ── الإثبات ── */
echo "قيمٌ ملوَّثةٌ حُجرت وفُرِّغت: {$total} · نُقل نصُّها إلى الملاحظات: {$moved}\n";
$left = 0;
foreach ($TARGETS as $tbl => $cols) {
    foreach ($cols as $col) {
        $q = $conn->query("SELECT COUNT(*) c FROM `{$tbl}` WHERE `{$col}` LIKE '%{$MARK}%'");
        if ($q) { $left += (int) $q->fetch_assoc()['c']; }
    }
}
$rowsKept = (int) $conn->query("SELECT COUNT(*) c FROM mnt_order")->fetch_assoc()['c'];
$qn = (int) $conn->query("SELECT COUNT(*) c FROM uat_field_quarantine")->fetch_assoc()['c'];
echo "ما زال ملوَّثًا: {$left} · وصفوفُ أوامرِ الصيانةِ كلُّها باقية: {$rowsKept} (صفرُ حذفِ صفّ)\n";
echo "سجلُّ الحجر: {$qn} قيمةً · الحذفُ بعد {$purge}\n";
if ($left > 0) { exit("✗ بقي تلوّثٌ — يُراجَع\n"); }
echo "✔ الخاناتُ نظيفةٌ · والنصُّ في موضعِه · والاستعادةُ ممكنةٌ حتى {$purge}:\n";
echo "   UPDATE mnt_order o JOIN uat_field_quarantine q ON q.table_name='mnt_order' AND q.row_id=o.id SET o.maint_type=q.polluted_value WHERE q.column_name='maint_type';\n";
