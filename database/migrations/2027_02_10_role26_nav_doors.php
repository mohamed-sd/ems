<?php
/**
 * 2027_02_10 — أبوابُ دورِ التمويل (26): توزيعٌ بالسابقةِ لا تكديسٌ في DAILY
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقيس**: للدور 26 («إدارة التمويل») 44 عنصرَ تنقُّلٍ — **41 منها في
 *   `DAILY`**، فبابا `FIN` و`GOV` فارغانِ عنده تمامًا مع أنه دورُ التمويلِ
 *   نفسُه. والتصميمُ التسعُ أبوابٍ يفقد معناه إن كُدِّس كلُّ شيءٍ في بابٍ واحد.
 *
 * ◆ **والبابُ لكلِّ مسارٍ مقيسٌ من سابقةٍ حيّةٍ لا مُختار**:
 *     `Financing/financiers_registry.php`      → `FIN`  (سابقةُ الدور 1)
 *     `Financing/financing_operation_new.php`  → `FIN`  (سابقةُ الدور 1)
 *     `Governance/entities_registry.php`       → `GOV`  (سابقةُ الدورين 1 و19)
 *     `Governance/signing_authority.php`       → `GOV`  (سابقةُ الدورين 1 و19)
 *     `Governance/licenses_guarantees.php`     → `GOV`  (سابقةُ الدورين 1 و19)
 *     `Reports/approval_lag_report.php`        → `REP`  (لا سابقةَ — والبابُ
 *        اسمُه «التقارير والتحليلات» وهو تقريرٌ حرفًا؛ فأولُ ساكنٍ لا مخترَع)
 *   و`FIN` خلفَ بوابةِ المجالِ المقيَّد (FIN-01 §1.1) — ودورُ التمويلِ صاحبُها.
 *
 * ◆ **وما لا يُنقل**: `Financing/financing_board.php` يبقى في `DAILY`.
 *   `fin26_role_test` يشترطه في `HOME` — و**ذاك يخالف التوحيدَ**: `HOME` فيه
 *   `main/my_workspace.php` في **25 دورًا**، و`unified_nav_test` يشترط «صفٌّ
 *   واحدٌ لكل دورٍ نشط» في بابِ الرئيسية. فنقلُه إلى `HOME` يجعل للدور 26
 *   صفَّين فيكسر فاحصًا آخرَ بحقّ. ولوحةُ الدورِ بيتُها `DAILY` كما هي
 *   `main/role_board.php` عنده الآن.
 *
 * ◆ ولا يُلمَس عنصرٌ لدورٍ آخرَ: الشرطُ `role_id = 26` حصرًا.
 * ◆ مُتحمِّلٌ للتكرار · ويُعَدُّ التوزيعُ بعده.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ROLE = 26;
$MOVE = array(
    'Financing/financiers_registry.php'     => 'FIN',
    'Financing/financing_operation_new.php' => 'FIN',
    'Governance/entities_registry.php'      => 'GOV',
    'Governance/signing_authority.php'      => 'GOV',
    'Governance/licenses_guarantees.php'    => 'GOV',
    'Reports/approval_lag_report.php'       => 'REP',
);

echo "══ أبوابُ الدور {$ROLE} ══\n";
$moved = 0;
foreach ($MOVE as $route => $door) {
    $st = $db->prepare("UPDATE nav_items SET door = ?
                         WHERE role_id = ? AND route = ? AND door <> ?");
    $st->bind_param('siss', $door, $ROLE, $route, $door);
    $st->execute();
    $n = $st->affected_rows;
    $st->close();
    if ($n > 0) { $moved += $n; echo "  ✔ {$route} ⇒ {$door} ({$n})\n"; }
    else        { echo "  ○ {$route} في {$door} سلفًا أو غيرُ موجود\n"; }
}

/* الأشكالُ المُرفَقةُ بلاصقةٍ (#cmp03 ونحوه) تتبع أصلَها — وإلا افترقا */
foreach ($MOVE as $route => $door) {
    $like = $db->real_escape_string($route) . '#%';
    $db->query("UPDATE nav_items SET door = '" . $db->real_escape_string($door) . "'
                 WHERE role_id = {$ROLE} AND route LIKE '{$like}'
                   AND door <> '" . $db->real_escape_string($door) . "'");
    if ($db->affected_rows > 0) {
        $moved += $db->affected_rows;
        echo '  ✔ ' . $route . "#… ⇒ {$door} ({$db->affected_rows})\n";
    }
}

echo "\n── توزيعُ أبوابِ الدور {$ROLE} بعده\n";
$r = $db->query("SELECT door, COUNT(*) c FROM nav_items WHERE role_id = {$ROLE}
                  GROUP BY door ORDER BY c DESC");
while ($x = $r->fetch_assoc()) { echo '  ' . str_pad($x['door'], 10) . $x['c'] . "\n"; }

$home = (int) $db->query("SELECT COUNT(*) FROM nav_items WHERE role_id = {$ROLE} AND door = 'HOME'")
                 ->fetch_row()[0];
echo '  ◆ بابُ الرئيسيةِ صفٌّ واحدٌ (شرطُ unified_nav): ' . ($home === 1 ? '✔ نعم' : '✘ ' . $home) . "\n";
echo "\n✅ نُقل {$moved} عنصرًا بالسابقةِ المقيسة.\n";
exit($home === 1 ? 0 : 1);
