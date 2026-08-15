<?php
/**
 * 2027_04_02_guard_classification_nav.php
 * ═══════════════════════════════════════════════════════════════════════════
 * رابطُ «تصنيف قواعد المنع» في القائمة — ⇐ INJ-0250
 *
 * **المقيس**: `Settings/guard_classification.php` مسجَّلةٌ في `modules` (#205)
 * وممنوحةٌ للدورين ١ و١٩، **وصفرُ صفِّ تنقّلٍ يشير إليها**. فهي تُفتح من بلاطاتِ
 * الوصولِ السريعِ وحدَها — ومن لا يعرف وجودَها لا يجدها.
 *
 * ── والقرار: تبقى هي المالكةَ للتصنيف ─────────────────────────────────────
 * الشاشتانِ المتنازعتان:
 *   · `Settings/guard_classification.php` — **تكتب فعلًا** (موضعُ كتابةٍ حقيقيّ).
 *   · `Governance/sensitive_fields.php` — تكتب في المخزنِ البينيِّ
 *     `cmp03_screen_rows` لا في جدولِ التصنيف. فهي **شاشةُ سياسةٍ** لا محرّكُ
 *     تصنيف.
 * فلا تنازعَ في الحقيقة: الأولى تصنّف، والثانية تُوثّق السياسة. والناقصُ
 * كان **الرابط** — فأُضيف.
 *
 * ◆ والصفُّ يحمل **وحدةَ صلاحياتٍ غيرَ فارغةٍ مع رمزِها** — فصفٌّ بوحدةٍ فارغةٍ
 *   ورمزٍ غيرِ فارغٍ يسقط في الفحصِ صامتًا (عيب FN-02).
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

echo "══ رابطُ «تصنيف قواعد المنع» ══\n\n";

$CODE = 'Settings/guard_classification.php';
$LABEL = 'تصنيف قواعد المنع';

$modId = 0;
$st = $conn->prepare('SELECT id FROM modules WHERE code = ? LIMIT 1');
$st->bind_param('s', $CODE);
$st->execute();
$x = $st->get_result()->fetch_row();
$st->close();
if ($x) { $modId = (int) $x[0]; }
if ($modId === 0) { exit("الشاشةُ غيرُ مسجَّلةٍ في `modules` — لا يُضاف رابطٌ لغيرِ مسجَّل\n"); }
echo "  المودول: #{$modId}\n";

/* الأدوارُ التي تملك عرضَها — ولا يُضاف رابطٌ لدورٍ لا يملكه (وإلا رابطٌ يردُّ 403) */
$roles = array();
$r = $conn->query('SELECT rp.role_id FROM role_permissions rp
                    WHERE rp.module_id = ' . $modId . ' AND rp.can_view = 1');
while ($r && ($y = $r->fetch_row())) { $roles[] = (int) $y[0]; }
echo '  أدوارٌ تملك عرضَها: ' . (count($roles) ? implode(' · ', $roles) : 'صفر') . "\n";
if (!$roles) { exit("  · لا دورَ يملك عرضَها — لا يُضاف رابطٌ يردُّ 403\n"); }

$added = 0; $seen = 0;
foreach ($roles as $rid) {
    $st = $conn->prepare('SELECT id FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1');
    $rs = (string) $rid;
    $st->bind_param('ss', $rs, $CODE);
    $st->execute();
    $has = (bool) $st->get_result()->fetch_row();
    $st->close();
    if ($has) { $seen++; echo "  · دور {$rid} له الرابطُ سلفًا\n"; continue; }

    /* المجموعةُ والبابُ من صفٍّ حيٍّ للدورِ نفسِه — فلا يُخترع بابٌ ولا مجموعة */
    $grp = null; $door = 'DAILY';
    $q = $conn->prepare("SELECT door, group_id FROM nav_items
                          WHERE role_id = ? AND group_id IS NOT NULL
                          ORDER BY sort_order DESC LIMIT 1");
    $q->bind_param('s', $rs);
    $q->execute();
    $g = $q->get_result()->fetch_assoc();
    $q->close();
    if ($g) { $door = (string) $g['door']; $grp = (int) $g['group_id']; }

    $ins = $conn->prepare('INSERT INTO nav_items
        (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 950, ?, 1)');
    if (!$ins) { echo "  ✘ دور {$rid}: {$conn->error}\n"; continue; }
    $icon = 'fa fa-shield-halved';
    $ins->bind_param('ssisssss', $rs, $door, $grp, $modId, $LABEL, $CODE, $icon, $CODE);
    if ($ins->execute()) { $added++; echo "  ✔ أُضيف للدور {$rid}\n"; }
    else { echo "  ✘ دور {$rid}: {$ins->error}\n"; }
    $ins->close();
}
echo "\n  المُضاف: {$added} · القائمُ سلفًا: {$seen}\n";
exit(0);
