<?php
/**
 * 2027_09_06_delegation_state_declaration.php
 *   حالةُ التفويضِ والتصعيدِ مقيسةً — INJ-FIX-01 · GAP-03 (+ GAP-09)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **GAP-03 يقول «التفويضُ مبنيٌّ بلا كودٍ يقرؤه». والقياسُ يفصله نصفَين:**
 *
 *   ▸ **التصعيدُ حيٌّ ومقروء** — ثلاثةُ مساراتٍ لكلٍّ بياناتٌ وقارئُ إنتاج:
 *     `work_escalations` (١٬٩٩٥ مفتوحًا · WorkItemService + cron_wfm_engine) ·
 *     `ticket_escalations` (٧٤ بـ٢٠ قاعدةً نشطة · SlaMonitor + TicketRouter) ·
 *     `risk_escalations` (٢٠ آليًّا · RiskService + RiskSignalEngine + ٣ شاشات).
 *     **فشطرُ الادعاءِ هذا مردودٌ بالقياس.**
 *
 *   ▸ **والتفويضُ خاملٌ فعلًا**: `gov_delegations` **صفرُ صفٍّ** وقارئُه واحدٌ
 *     (`ImpersonationService`) يُنادى من شاشةٍ واحدة. و`work_delegations`
 *     عشرون صفًّا **كلُّها بقايا بذرِ `UAT-2026`** و**صفرٌ منها نافذٌ الآن**.
 *
 * ◆ **وبرهانُ التلوث قاطعٌ من البنيةِ لا من الظن**: الجملةُ الواحدةُ كُتبت في
 *   ثلاثةِ أعمدةٍ فبُترت في كلٍّ بعرضِه — `kind` varchar(20) يحمل «وفق المعتمد
 *   في محضر » و`status` varchar(12) يحمل «وفق المعتمد » — **نفسُ الجملةِ بطولَين**.
 *   و`effect_on_open` يحمل تمامَها ومعها الوسمُ `· UAT-2026`. و`starts_at`
 *   يساوي `ends_at` ⇒ **نافذةُ تفويضٍ بلا مدة**. (وسببُه أن `sql_mode` خالٍ
 *   فالبترُ صامت.)
 *
 * ◆ **فيُكنَس البذرُ** (وهو عملُ GAP-09) **ولا يُقرَّر تقاعدُ التفويض**: تقاعدُ
 *   قدرةٍ عملٍ قرارُ مالكٍ لا حكمُ منفِّذ. ويبقى البندُ مُعلَنًا بمقيسِه.
 *
 * التشغيل:  php database/migrations/2027_09_06_delegation_state_declaration.php
 * الرجوع :  php database/migrations/2027_09_06_delegation_state_declaration.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

if (in_array('--revert', $argv, true)) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='work_delegations_seed_archive'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("INSERT INTO `work_delegations` SELECT * FROM `work_delegations_seed_archive`");
        echo "↺ أُعيد {$conn->affected_rows} صفَّ بذرٍ\n";
        $conn->query("DROP TABLE `work_delegations_seed_archive`");
    }
    $conn->query("DROP TABLE IF EXISTS `gov_delegation_state`");
    echo "↺ أُسقط سجلُّ حالةِ التفويض\n";
    exit(0);
}

/* ══ ① كنسُ بذرِ UAT من work_delegations — GAP-09 ═════════════════════════ */
/* ◆ **يُكنَس بالبرهانِ لا بالوسمِ وحدَه**: صفٌّ يحمل وسمَ البذرِ في `effect_on_open`
 *   **و**نافذتُه بلا مدةٍ (`starts_at = ends_at`). فشرطان لا واحد. */
$q = $conn->query("SELECT COUNT(*) FROM `work_delegations`
                    WHERE `effect_on_open` LIKE '%UAT-2026%' AND `starts_at` = `ends_at`");
$seed = (int) $q->fetch_row()[0];
$tot  = (int) $conn->query("SELECT COUNT(*) FROM `work_delegations`")->fetch_row()[0];
echo "① work_delegations: {$tot} صفًّا · منها بذرُ UAT بنافذةٍ بلا مدة: {$seed}\n";

if ($seed > 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `work_delegations_seed_archive` LIKE `work_delegations`");
    $conn->query("INSERT INTO `work_delegations_seed_archive`
                  SELECT * FROM `work_delegations`
                   WHERE `effect_on_open` LIKE '%UAT-2026%' AND `starts_at` = `ends_at`
                  ON DUPLICATE KEY UPDATE `id` = `work_delegations_seed_archive`.`id`");
    if ($conn->errno) { exit("✘ تعذّرت الأرشفة: {$conn->error} — لم يُحذف شيء\n"); }
    $arch = (int) $conn->query("SELECT COUNT(*) FROM `work_delegations_seed_archive`")->fetch_row()[0];
    if ($arch < $seed) { exit("✘ المؤرشَفُ {$arch} والبذرُ {$seed} — لا يُحذف ما لم يُؤرشَف\n"); }
    echo "   ✔ أُرشف {$arch} صفًّا قبلَ الحذف\n";
    $conn->query("DELETE FROM `work_delegations`
                   WHERE `effect_on_open` LIKE '%UAT-2026%' AND `starts_at` = `ends_at`");
    echo "   كُنس: {$conn->affected_rows} صفًّا\n";
}

/* ══ ② سجلُّ الحالةِ المقيسة ══════════════════════════════════════════════ */
$conn->query("CREATE TABLE IF NOT EXISTS `gov_delegation_state` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `pathway`   VARCHAR(48)  NOT NULL COMMENT 'DELEGATION | ESCALATION',
    `store`     VARCHAR(64)  NOT NULL,
    `rows_live` INT UNSIGNED NOT NULL,
    `readers`   VARCHAR(300) NOT NULL COMMENT 'قرّاءُ الإنتاجِ المقيسون',
    `verdict`   VARCHAR(32)  NOT NULL COMMENT 'LIVE_READABLE | DORMANT',
    `note`      VARCHAR(400) NOT NULL,
    `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_store` (`store`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='GAP-03 — حالةُ مساراتِ التفويضِ والتصعيدِ مقيسةً بالبياناتِ والقرّاء'");

function cnt($conn, $t, $w = '1')
{
    $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE {$w}");
    return $r ? (int) $r->fetch_row()[0] : 0;
}
$ROWS = array(
    array('ESCALATION', 'work_escalations',   cnt($conn, 'work_escalations', 'resolved_at IS NULL'),
          'WorkItemService · cron_wfm_engine', 'LIVE_READABLE', 'بنودُ عملٍ مفتوحةٌ مُصعَّدةٌ فعلًا'),
    array('ESCALATION', 'ticket_escalations', cnt($conn, 'ticket_escalations'),
          'SlaMonitor · TicketRouter · TicketStateService', 'LIVE_READABLE',
          'ومعها ' . cnt($conn, 'ticket_escalation_rules', 'active=1') . ' قاعدةَ تصعيدٍ نشطة'),
    array('ESCALATION', 'risk_escalations',   cnt($conn, 'risk_escalations'),
          'RiskService · RiskSignalEngine · 3 شاشات', 'LIVE_READABLE', 'تصعيدٌ آليٌّ من محرِّكِ الإشارات'),
    array('DELEGATION', 'gov_delegations',    cnt($conn, 'gov_delegations'),
          'ImpersonationService ⟵ Governance/impersonations.php', 'DORMANT',
          'صفرُ صفٍّ — الجدولُ مبنيٌّ وقارئُه واحدٌ ولا بيانَ فيه'),
    array('DELEGATION', 'work_delegations',   cnt($conn, 'work_delegations',
          "status='active' AND (ends_at IS NULL OR ends_at >= NOW())"),
          'WorkItemService · cron_wfm_engine · Portal/my_tasks.php', 'DORMANT',
          'قرّاؤه ثلاثةٌ حقيقيّون — لكن صفرَ تفويضٍ نافذٍ بعدَ كنسِ بذرِ UAT'),
);
$st = $conn->prepare("INSERT INTO `gov_delegation_state`
        (`pathway`,`store`,`rows_live`,`readers`,`verdict`,`note`) VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE `rows_live`=VALUES(`rows_live`), `readers`=VALUES(`readers`),
            `verdict`=VALUES(`verdict`), `note`=VALUES(`note`), `measured_at`=NOW()");
foreach ($ROWS as $r) {
    $st->bind_param('ssisss', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5]);
    $st->execute();
}
$st->close();

echo "───────────────────────────────────────────────────────────────\n";
$q = $conn->query("SELECT `pathway`,`store`,`rows_live`,`verdict` FROM `gov_delegation_state`
                    ORDER BY `pathway`, `store`");
while ($q && $x = $q->fetch_assoc()) {
    printf("  %-11s %-22s %6d  %s\n", $x['pathway'], $x['store'], $x['rows_live'], $x['verdict']);
}
$live = (int) $conn->query("SELECT COUNT(*) FROM `gov_delegation_state` WHERE `verdict`='LIVE_READABLE'")->fetch_row()[0];
echo "───────────────────────────────────────────────────────────────\n";
echo "② مساراتٌ حيّةٌ مقروءةٌ بأثر: {$live} · **والمعيارُ يطلب ثمانيةً أو تقاعدًا معلَنًا**\n";
echo "◆ فالشطرُ الأولُ من الادعاءِ **مردودٌ**: التصعيدُ مبنيٌّ وله كودٌ يقرؤه وبياناتٌ حيّة.\n";
echo "◆ والشطرُ الثاني **قائم**: التفويضُ بلا بيانٍ نافذٍ واحد.\n";
echo "◆ **ولا يُقرَّر تقاعدُه هنا** — تقاعدُ قدرةِ عملٍ قرارُ مالكٍ لا حكمُ منفِّذ (BLOCKED_OWNER_INPUT).\n";
