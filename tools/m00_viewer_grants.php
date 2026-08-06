<?php
/**
 * tools/m00_viewer_grants.php — منح «العارض» للدور التنفيذي (M-00 §9-2) · v1
 * ═══════════════════════════════════════════════════════════════════════════
 * مصفوفة الصلاحيات في الوثيقة تجعل التنفيذي **عارضًا** على 11 شاشة يقرؤها
 * ولا يملكها (§8-3): التفويض بالتوقيع · سجل العقود الموحَّد · الهيكل
 * والتكليفات · المخاطر التجارية · القوائم المالية · هامش الربح · مؤشرات
 * البلاغات · إنجازي · بوابتي · طلباتي. المنح **can_view فقط** — صفر كتابة،
 * التزامًا بـBR-CEO-06 (لا تنفيذَ ولا إدخالَ من القمة).
 *
 * php tools/m00_viewer_grants.php            → تجريب (عرض الناقص)
 * php tools/m00_viewer_grants.php --apply    → منح العرض للناقص
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* الموديولات كما تشير إليها قائمة الدور 9 في nav_items (فحص 2026-08-06) */
$VIEWER_MODULES = array(207, 151, 217, 78, 100, 188, 216, 202, 137, 187, 115);

$missing = array();
foreach ($VIEWER_MODULES as $mid) {
    $r = $conn->query("SELECT m.id, m.name,
            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = 9 AND rp.module_id = m.id) AS has_row
        FROM modules m WHERE m.id = {$mid}");
    $w = $r ? $r->fetch_assoc() : null;
    if (!$w) { $o("  ⚠ موديول {$mid} غير موجود — يُتخطى معلَنًا"); continue; }
    if ((int) $w['has_row'] > 0) { $o("  ✓ {$w['name']} (#{$mid}) — منحة قائمة"); continue; }
    $missing[] = array((int) $w['id'], (string) $w['name']);
    $o("  ✗ {$w['name']} (#{$mid}) — بلا منحة عرض");
}
$o('الناقص: ' . count($missing));
if (!$APPLY) { $o('تجريب — أعد بـ --apply.'); exit(0); }

$st = $conn->prepare("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES (9, ?, 1, 0, 0, 0)");
$ok = 0;
foreach ($missing as $mrow) {
    $st->bind_param('i', $mrow[0]);
    if ($st->execute()) { $ok++; $o("  + عرضٌ فقط: {$mrow[1]}"); }
    else { $o("  ✗ {$mrow[1]}: " . $st->error); }
}
$st->close();
$o("مُنح {$ok} — والكتابة صفر (BR-CEO-06)");
$o('تم ✅');
