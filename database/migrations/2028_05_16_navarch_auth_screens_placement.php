<?php
/**
 * 2028_05_16_navarch_auth_screens_placement.php — إظهارُ شاشتَي النظامِ الحاكمِ
 * في سايدبارِ دورِ إدارةِ الصلاحيات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ مقيسٌ لا مُرتأى**: `Governance/auth_grants.php` و`auth_profiles.php`
 *   تعملان ويفتحهما الحارسُ (٢٠٠ بلا منع · ١٥٤ و٢٠٤ صفًّا) ولهما صفٌّ في
 *   `modules` و`nav_items` و`nav_canonical` و`gov_profile_items`، لكنّهما
 *   **صفرٌ في سجلَّي الموضع** — و`navarch_render()` لا يقرأ غيرَ
 *   `nav_workspace_placements`. فالبابُ مفتوحٌ ولا رابطَ يقود إليه.
 *
 * ◆ **والمقارنةُ حسمت الجذرَ**: `governance/sod_conflicts` مطابقٌ لهما في كلِّ
 *   سجلٍّ إلّا هذين الاثنين — وهو يظهر وهما لا يظهران.
 *
 * ◆ **رأسُ الطيِّ المختار 1360** «إدارة الصلاحيات والأدوار» — وفيه اليومَ
 *   كونسولُ الجدولِ القديمِ التسعُ. والحيُّ يسبق التاريخيَّ في الترتيب:
 *   ٦٩ و٧٠ قبلَ ٧١..٧٩.
 *
 * ⚠ **وفرقٌ مسمًّى لا مطويّ**: `nav_canonical.group_name` لهاتين يقول
 *   «الحوكمة والضوابط» و«الأدوار والصلاحيات» — ولم يُمَسّ صفٌّ معتمَدٌ
 *   بقرارِ مالك. الموضعُ سجلُّه غيرُ سجلِّ التسمية، والفرقُ يُذكر ليُحسم
 *   بقرارٍ لا بكتابةٍ صامتة.
 *
 * ⛔ **ولا يُمَسُّ جدولُ صلاحيّاتٍ ولا قالبٌ ولا منحة**: شرطُ التفويضِ قائمٌ
 *   سلفًا للدورِ ١٥ في الشاشتَين، وهذه كتابةُ **موضعٍ** لا كتابةُ إذن.
 *
 * التشغيل: php database/migrations/2028_05_16_navarch_auth_screens_placement.php
 * العكس:   php database/migrations/2028_05_16_navarch_auth_screens_placement_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$WS   = 'DEP-08';
$GID  = 1360;
$ROLE = 15;

/* المسارُ في كلِّ سجلٍّ بصيغتِه: `nav_placements` بلاحقةٍ و`nav_workspace_placements` بلا. */
$ITEMS = array(
    array('slug' => 'auth_profiles', 'screen' => 'SCR-0363', 'target' => 'NT-DEP-08-042',
          'label' => 'قوالب الصلاحيات', 'ws_sort' => 69, 'plc_sort' => 20, 'row_no' => 42),
    array('slug' => 'auth_grants',   'screen' => 'SCR-0362', 'target' => 'NT-DEP-08-043',
          'label' => 'منح الصلاحية',   'ws_sort' => 70, 'plc_sort' => 21, 'row_no' => 43),
);

$SRC = 'NAV-ARCH-02 §9 — شاشة حاكمة في دورة المساحة المالكة، والموضع كان ناقصا وحده';
$REF = 'قياس حي: مبني ومفوض وبلا موضع — والمقارنة بـ governance/sod_conflicts';

/* ── ① الشاهدُ قبلَ الكتابة: المُصيَّرُ لا المخزن ───────────────────────────── */
require_once $ROOT . '/includes/navarch_renderer.php';
$rendered = function ($conn, $ws, $role) {
    $tree = navarch_render($conn, $ws, $role, array('include_shell' => false));
    $out = array();
    foreach ($tree['groups'] as $g) {
        foreach ($g['items'] as $it) { $out[$it['route']] = $g['label']; }
    }
    return $out;
};
$before = $rendered($conn, $WS, $ROLE);
echo "══ ① قبلَ الكتابة — المُصيَّرُ لدورِ {$ROLE} ═══════════════════════════════\n";
printf("   بنودٌ مُصيَّرة: %d\n", count($before));
foreach ($ITEMS as $it) {
    $r = 'governance/' . $it['slug'];
    printf("   %-30s %s\n", $r, isset($before[$r]) ? 'ظاهر' : 'غائب');
}

/* ── ② السجلّاتُ الثلاثة، ولا يُدهَس صفٌّ قائم ──────────────────────────────── */
echo "\n══ ② الكتابة ═════════════════════════════════════════════════════════\n";
$wrote = array('nav_targets' => 0, 'nav_placements' => 0, 'nav_workspace_placements' => 0);

foreach ($ITEMS as $it) {
    $lower   = 'governance/' . $it['slug'];
    $withPhp = $lower . '.php';
    $tref    = $WS . '·' . $it['row_no'] . '·' . $it['label'];

    /* ⓐ الهدف — و`nav_placements.target_id` مفتاحٌ أجنبيٌّ عليه فيسبقه */
    if ($one("SELECT COUNT(*) FROM nav_targets WHERE target_id = '" . $conn->real_escape_string($it['target']) . "'") < 1) {
        $gk = 'الادوار والصلاحيات';
        $st = $conn->prepare(
            "INSERT INTO nav_targets
               (target_id, source_doc, sheet_code, row_no, canonical_title,
                workspace_id, group_key, target_order, visibility_class, active)
             VALUES (?,?,?,?,?,?,?,?,'MENU_ITEM',1)");
        $st->bind_param('sssisssi', $it['target'], $SRC, $WS, $it['row_no'], $it['label'],
                        $WS, $gk, $it['row_no']);
        $st->execute(); $wrote['nav_targets'] += $st->affected_rows; $st->close();
    }

    /* ⓑ موضعُ ورقةِ الدليل */
    if ($one("SELECT COUNT(*) FROM nav_placements WHERE route = '" . $conn->real_escape_string($withPhp) . "'") < 1) {
        $st = $conn->prepare(
            "INSERT INTO nav_placements
               (workspace_id, screen_id, route, target_ref, target_id, group_id,
                sort_no, placement_type, source_ref, active)
             VALUES (?,?,?,?,?,?,?,'MENU_ITEM',?,1)");
        $st->bind_param('sssssiis', $WS, $it['screen'], $withPhp, $tref, $it['target'],
                        $GID, $it['plc_sort'], $REF);
        $st->execute(); $wrote['nav_placements'] += $st->affected_rows; $st->close();
    }

    /* ⓒ **السجلُّ الحاكمُ للتصيير** — ومعرِّفُه مشتقٌّ من المسارِ فيثبت بين التشغيلات */
    if ($one("SELECT COUNT(*) FROM nav_workspace_placements WHERE route = '" . $conn->real_escape_string($lower) . "'") < 1) {
        $pid = 'WP-' . strtoupper(substr(md5($WS . '|' . $lower), 0, 16));
        $st = $conn->prepare(
            "INSERT INTO nav_workspace_placements
               (placement_id, screen_id, workspace_id, group_id, placement_type, sort_no,
                route, canonical_label, governing_source, source_ref, reason_code,
                effective_from, status, version, created_by, legacy_ref, created_at)
             VALUES (?,?,?,?,'PRIMARY',?,?,?,?,?,'GUIDE_OWNED_LIFECYCLE_S9',
                     CURDATE(),'ACTIVE',1,?,?,NOW())");
        $by = 'migrations/2028_05_16_navarch_auth_screens_placement';
        $st->bind_param('sssiisssssi', $pid, $it['screen'], $WS, $GID, $it['ws_sort'],
                        $lower, $it['label'], $SRC, $REF, $by, $GID);
        $st->execute(); $wrote['nav_workspace_placements'] += $st->affected_rows; $st->close();
    }
}
foreach ($wrote as $t => $n) { printf("   %-28s صفوفٌ مضافة: %d\n", $t, $n); }

/* ── ③ الشاهدُ بعدَ الكتابة، ومعه ضابطٌ سالبٌ لدورٍ لا يملكها ────────────────── */
echo "\n══ ③ بعدَ الكتابة ════════════════════════════════════════════════════\n";
$after = $rendered($conn, $WS, $ROLE);
printf("   بنودٌ مُصيَّرة: %d (‏كانت %d)\n", count($after), count($before));
$ok = true;
foreach ($ITEMS as $it) {
    $r = 'governance/' . $it['slug'];
    $seen = isset($after[$r]);
    if (!$seen) { $ok = false; }
    printf("   %-30s %s%s\n", $r, $seen ? 'ظاهر' : 'غائب',
           $seen ? ' تحت راس الطي: ' . $after[$r] : '');
}
$other = $rendered($conn, $WS, 1);
$leak = 0;
foreach ($ITEMS as $it) { if (isset($other['governance/' . $it['slug']])) { $leak++; } }
printf("   الضابط السالب — الدور 1 يراهما: %d (المستهدف صفر)\n", $leak);

if (!$ok || $leak > 0) { exit("\n⛔ الشاهدُ لم يتحقّق — راجعِ الموضعَ قبلَ الالتزام.\n"); }

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn);
echo "\n✔ تمّ — والشاهدُ من المُصيَّرِ لا من الجدول.\n";
