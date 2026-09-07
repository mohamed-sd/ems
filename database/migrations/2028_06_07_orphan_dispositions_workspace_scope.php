<?php
/**
 * 2028_06_07 — أحكامُ اليتامى تُسمّي **مساحتَها** · تصحيحُ 2028_06_04
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **العطبُ في هجرتي السابقة**: كتبت 78 حكمًا بـ`current_workspace = ''`،
 *   لأنّها اشتقّت المساحةَ من `nav_placements.workspace_id` — **وهو فارغٌ لهذه
 *   الشاشاتِ بعينِها، وذاك سببُ يُتمِها أصلًا**. فاشتقاقُ صفةِ اليتيمِ من
 *   السجلِّ الذي لا يعرفه دورٌ لا يُنتج شيئًا.
 *
 * ◆ **وحكمٌ بلا مساحةٍ لا يُجيب السؤالَ المطروح**: بوّابةُ «صفرِ الفقد»
 *   (`tools/uxui_preserve_check.php`) تسأل — لكلِّ دورٍ على حدة — **«لماذا اختفى
 *   هذا الرابطُ من سايدبارِ هذه المساحة؟»**، وتقرأ:
 *       `nav_legacy_disposition WHERE current_workspace = <مساحةُ الدور>`
 *   فحكمٌ بمساحةٍ فارغةٍ يقول «هذه الشاشةُ فجوة» ولا يقول **في أيِّ قائمةٍ**.
 *   ⇒ ولذلك **حجبت البوّابةُ الالتزام**: دور 32 · `tickets/my_tickets.php`
 *     «ناقص: الملفُّ كلُّه» — وله حكمٌ، لكنَّه لا يخصُّ مساحتَه.
 *
 * ◆ **والمصدرُ الصحيحُ للمساحة: مساحةُ كلِّ دورٍ يسمح له قالبُه بالشاشة** —
 *   أي المكانُ الذي **كان يُتوقَّع** أن يظهر فيه الرابط. فالحكمُ الواحدُ يتوزَّع
 *   على المساحاتِ التي يخصُّها: 78 حكمًا ⇒ **260 صفًّا** بمساحاتٍ مسمّاة.
 *
 * ⛔ **ولا يتغيَّر حكمٌ واحد**: `disposition` و`action` و`decided_level` و`evidence`
 *   تُنقَل كما هي حرفًا. المضافُ **المساحةُ وحدَها** — تسميةُ محلِّ الحكمِ لا
 *   تبديلُه. وما لا يُعرَف له دورٌ مسموحٌ يبقى بصفِّه الأصليِّ بلا مساحة.
 *
 * ◆ **ومُعاوَدة**: تشغيلٌ ثانٍ يجد صفرَ حكمٍ بلا مساحةٍ فيُعلن ذلك ويخرج.
 * التشغيل: php database/migrate.php up
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

require_once $ROOT . '/includes/navarch_renderer.php';

$REF = 'NAV-ARCH-02 §22 · جولةُ أحكامِ اليتامى 2028-06-04';

/* ── الأحكامُ بلا مساحة ─────────────────────────────────────────────────── */
$orphans = array();
$st = $conn->prepare("SELECT * FROM nav_legacy_disposition
                       WHERE decision_ref = ? AND (current_workspace = '' OR current_workspace IS NULL)");
$st->bind_param('s', $REF); $st->execute();
$r = $st->get_result();
while ($x = $r->fetch_assoc()) { $orphans[] = $x; }
$st->close();

echo "  ◦ أحكامٌ بلا مساحة: " . count($orphans) . "\n";
if (!$orphans) {
    echo "  ✔ لا شيءَ يُصحَّح — كلُّ حكمٍ يسمّي مساحتَه\n";
    require_once __DIR__ . '/_ledger.php';
    ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
    return;
}

/* ── مساحةُ كلِّ دورٍ حيّ ────────────────────────────────────────────────── */
$wsOfRole = array();
$rr = $conn->query("SELECT DISTINCT role FROM users
                     WHERE is_deleted=0 AND status='active' AND company_id=4");
while ($rr && ($x = $rr->fetch_row())) {
    $w = navarch_role_workspace($conn, (int) $x[0]);
    if ($w) { $wsOfRole[(int) $x[0]] = $w; }
}

$ins = $conn->prepare("INSERT INTO nav_legacy_disposition
        (legacy_item_id, screen_id, current_workspace, current_label, current_route,
         usage_count, disposition, action, reason, decision_ref, evidence,
         decided_level, retire_stage, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'NONE',NOW())
        ON DUPLICATE KEY UPDATE current_workspace = VALUES(current_workspace),
                                evidence = VALUES(evidence)");
$del = $conn->prepare("DELETE FROM nav_legacy_disposition WHERE legacy_item_id = ?");
$q   = $conn->prepare("SELECT DISTINCT u.role FROM gov_authority_grants g
        JOIN gov_role_profiles p ON p.profile_id=g.profile_id AND p.state='active'
        JOIN gov_profile_items i ON i.profile_id=p.profile_id AND i.item_kind='screen' AND i.allow=1
        JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active' AND u.company_id=4
       WHERE g.revoked_at IS NULL AND LOWER(i.item_ref) = LOWER(?)");
if (!$ins || !$del || !$q) { exit("prepare فشل: {$conn->error}\n"); }

$made = 0; $kept = 0; $dropped = 0;
foreach ($orphans as $o) {
    $q->bind_param('s', $o['current_route']);
    $q->execute();
    $res = $q->get_result();
    $spaces = array();
    while ($y = $res->fetch_row()) {
        $w = isset($wsOfRole[(int) $y[0]]) ? $wsOfRole[(int) $y[0]] : null;
        if ($w) { $spaces[$w] = 1; }
    }
    $spaces = array_keys($spaces);
    if (!$spaces) { $kept++; continue; }          /* لا دورَ حيٌّ ⇒ يبقى كما هو */

    foreach ($spaces as $w) {
        $lid = 'ORPHW-' . strtoupper(substr(md5(strtolower($o['current_route']) . '|' . $w), 0, 10));
        $ev  = (string) $o['evidence'] . ' · المساحة ' . $w . ' (مساحةُ دورٍ مسموحٍ له)';
        $sid = $o['screen_id'];
        $ins->bind_param('sssssissssss', $lid, $sid, $w, $o['current_label'], $o['current_route'],
                         $o['usage_count'], $o['disposition'], $o['action'], $o['reason'],
                         $o['decision_ref'], $ev, $o['decided_level']);
        if (!$ins->execute()) { exit("execute فشل: {$ins->error}\n"); }
        $made++;
    }
    /* الصفُّ بلا مساحةٍ يُسقَط — بديلُه أدقُّ منه */
    $del->bind_param('s', $o['legacy_item_id']);
    $del->execute();
    $dropped++;
}
$ins->close(); $del->close(); $q->close();

echo "  ✔ صفوفٌ بمساحاتٍ مسمّاة : {$made}\n";
echo "  ✔ صفوفٌ بلا مساحةٍ أُسقطت: {$dropped}\n";
echo "  ◦ بقيت بلا مساحة (لا دورَ حيّ): {$kept}\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
