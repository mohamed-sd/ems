<?php
/**
 * 2027_09_28_chain_bypassed_units_restore.php
 *   خمسُ وحداتٍ بلغت حالةً بلا سلّمِها — تُعاد إلى ما يسنده دليلُها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفته البوابةُ الثانية**: خمسةُ صفوفٍ في `unit_entries` حالتُها
 *   `sales_approved` — أي «اكتملت سلسلتُها التجارية» — **وفيها صفرُ قرارِ
 *   اعتمادٍ من أيِّ مرحلة**. كُتبت الحالةُ مباشرةً فقفزت السلسلةَ كلَّها.
 *   وهو عينُ ما تمنعه بوابةُ «السلّم لا يُتجاوَز».
 *
 * ◆ **ولا أثرَ لها في المصبّ — مقيسًا لا مفترَضًا**: صفرُ أثرٍ ماليّ، صفرُ
 *   إسنادِ طرف، صفرُ سطرِ زمن، صفرُ واقعةٍ منشورة، صفرُ قرارِ اعتماد.
 *   ⇒ **فإعادتُها إلى `submitted` تصحيحٌ لا فقد**: الواقعةُ مسجَّلةٌ كما وقعت،
 *   وتنتظر اعتمادَ موقعِها كما توجب الآلة.
 *
 * ◆ **ولا يُحذف صفٌّ**: الحالةُ السابقةُ محفوظةٌ في سجلِّ التصحيحِ الحاكم،
 *   والرجوعُ يعيدها حرفًا.
 *
 * التشغيل:  php database/migrations/2027_09_28_chain_bypassed_units_restore.php
 * الرجوع :  php database/migrations/2027_09_28_chain_bypassed_units_restore.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$conn->query("CREATE TABLE IF NOT EXISTS `gov_chain_state_corrections` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `company_id`  INT UNSIGNED NOT NULL,
  `entry_id`    INT UNSIGNED NOT NULL,
  `entry_no`    VARCHAR(30) NULL,
  `state_before` VARCHAR(24) NOT NULL,
  `state_after`  VARCHAR(24) NOT NULL,
  `reason`      VARCHAR(400) NOT NULL,
  `evidence`    VARCHAR(300) NOT NULL COMMENT 'ما قِيس في المصبِّ قبلَ التصحيح',
  `corrected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_csc_entry` (`entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='INJ-CHAIN-CLOSE-01 — تصحيحُ حالةٍ بلغت بلا سلّمِها · بالحالةِ السابقةِ ودليلِها'");

if (in_array('--revert', $argv, true)) {
    $q = $conn->query("SELECT `entry_id`,`state_before` FROM `gov_chain_state_corrections`");
    $back = 0;
    while ($q && $r = $q->fetch_assoc()) {
        $st = $conn->prepare("UPDATE `unit_entries` SET `state` = ? WHERE `id` = ?");
        $st->bind_param('si', $r['state_before'], $r['entry_id']);
        $st->execute(); $back += $st->affected_rows; $st->close();
    }
    $conn->query("DROP TABLE IF EXISTS `gov_chain_state_corrections`");
    echo "↺ أُعيد {$back} صفًّا إلى حالتِه السابقة · وأُسقط سجلُّ التصحيح\n";
    exit(0);
}

/* ── ① الرصدُ بالقاعدةِ لا بقائمةِ معرِّفاتٍ مثبَّتة ─────────────────────── */
$q = $conn->query("
  SELECT e.`id`, e.`company_id`, e.`entry_no`, e.`state`
    FROM `unit_entries` e
   WHERE e.`state` IN ('sales_approved','parties_approved','converted')
     AND NOT EXISTS (SELECT 1 FROM `unit_approvals` a WHERE a.`entry_id` = e.`id`)");
$rows = array();
while ($q && $r = $q->fetch_assoc()) { $rows[] = $r; }
printf("① وحداتٌ بلغت حالةَ اعتمادٍ بصفرِ قرارٍ مسجَّل: **%d**\n", count($rows));
if (!$rows) { echo "  ◆ لا شيءَ يُصحَّح — البوابةُ خضراءُ سلفًا\n"; ems_migration_recorded(__FILE__, $conn, 0); exit(0); }

/* ── ② التحقُّقُ من خلوِّ المصبِّ — ولا يُصحَّح صفٌّ له أثر ─────────────── */
$ins = $conn->prepare("INSERT INTO `gov_chain_state_corrections`
      (`company_id`,`entry_id`,`entry_no`,`state_before`,`state_after`,`reason`,`evidence`)
      VALUES (?,?,?,?,'submitted',?,?)
      ON DUPLICATE KEY UPDATE `evidence` = VALUES(`evidence`)");
$upd = $conn->prepare("UPDATE `unit_entries` SET `state` = 'submitted' WHERE `id` = ? AND `state` = ?");
$reason = 'حالةُ اعتمادٍ بلغتها الواقعةُ بلا قرارٍ واحدٍ في سلسلتِها — قفزٌ للسلّم. '
        . 'وأُعيدت إلى `submitted`: الواقعةُ مسجَّلةٌ كما وقعت وتنتظر اعتمادَ موقعِها.';
$fixed = 0; $held = array();
foreach ($rows as $r) {
    $id = (int) $r['id'];
    $eff = array();
    foreach (array(
        'unit_effects'      => "SELECT COUNT(*) FROM `unit_effects` WHERE `source_unit_id` = {$id}",
        'unit_party_awards' => "SELECT COUNT(*) FROM `unit_party_awards` WHERE `source_kind`='unit_record' AND `source_ref` = {$id}",
        'unit_time_log'     => "SELECT COUNT(*) FROM `unit_time_log` WHERE `entry_id` = {$id}",
    ) as $k => $sql) {
        $x = $conn->query($sql);
        $eff[$k] = $x ? (int) $x->fetch_row()[0] : -1;
    }
    $total = array_sum($eff);
    $ev = 'أثرٌ ماليّ=' . $eff['unit_effects'] . ' · إسنادُ طرف=' . $eff['unit_party_awards']
        . ' · سطورُ زمن=' . $eff['unit_time_log'] . ' · قراراتُ اعتماد=0';
    if ($total !== 0) {
        /* ◆ **لا يُصحَّح صفٌّ له أثرٌ في المصبّ** — التصحيحُ حينئذٍ فقدٌ لا إصلاح */
        $held[] = "#{$id} ({$ev})";
        continue;
    }
    $ins->bind_param('iissss', $r['company_id'], $id, $r['entry_no'], $r['state'], $reason, $ev);
    $ins->execute();
    $upd->bind_param('is', $id, $r['state']);
    $upd->execute();
    if ($upd->affected_rows > 0) { $fixed++; }
}
$ins->close(); $upd->close();
printf("② صُحِّحت %d وحدةً — والحالةُ السابقةُ محفوظةٌ بدليلِها\n", $fixed);
if ($held) {
    printf("③ **مُمسَكٌ عن التصحيح** (لها أثرٌ في المصبّ): %d\n", count($held));
    foreach ($held as $h) { echo "   · {$h}\n"; }
    echo "   ◆ وتصحيحُ صفٍّ له أثرٌ **فقدٌ لا إصلاح** — يُحال إلى حكمِ مالك.\n";
} else {
    echo "③ لا صفَّ مُمسَكًا عنه — كلُّها خاليةُ المصبّ\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
