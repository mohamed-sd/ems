<?php
/**
 * tools/auditor_readonly.php — استقلالُ المراجعة: المراجعُ يقرأ ولا يكتب (M-14 §9)
 * ───────────────────────────────────────────────────────────────────────────
 * M-14 §9-1: «المراجعُ الداخلي — نطاقُه سجلُّ التدقيق · يملك قراءتَه وتحليلَه
 * ورفعَ الملاحظات · **ولا يملك الكتابةَ فيه ولا تعديلَ الحمايات**»، و«لا يجمع
 * حسابٌ بين تنفيذِ فعلٍ ومراجعةِ سجل تدقيقه».
 *
 * كشف مسحُ فصل الواجبات (tools/sod_sweep.php) أن الدور 20 «المراجع والمدقق
 * المالي» يملك الكتابةَ في اثنتين وعشرين شاشةً مالية — منها القيودُ اليومية
 * والمطابقةُ البنكية وإقفالُ الفترات. فمراجعٌ يكتب في الدفاتر التي يراجعها،
 * وهو نقضُ استقلالِ المراجعة من أصله.
 *
 * ما يفعله: يُطفئ رايات (add · edit · delete) عن الدور 20 ويُبقي العرضَ كاملًا —
 * فلا يفقد المراجعُ رؤيةَ شيء. ويستثني شاشتين شخصيتين لا علاقةَ لهما بالمراجعة
 * (تقييمي · فتحُ بلاغ) فحقُّ كلِّ موظفٍ فيهما محفوظ.
 *
 * php tools/auditor_readonly.php --diff | --apply
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

const AUDITOR_ROLE = 20;
/** حقوقٌ شخصيةٌ لكل موظف — خارج نطاق المراجعة فلا تُمسّ */
$KEEP = array('Portal/my_evaluation.php', 'Tickets/ticket_contextual_open.php');

$rows = array();
$r = mysqli_query($conn,
    "SELECT rp.id, m.code, m.name, rp.can_add, rp.can_edit, rp.can_delete
       FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
      WHERE rp.role_id = " . AUDITOR_ROLE . "
        AND (rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)
      ORDER BY m.code");
while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; }

$strip = array(); $keep = array();
foreach ($rows as $x) {
    if (in_array($x['code'], $KEEP, true)) { $keep[] = $x; } else { $strip[] = $x; }
}

echo "════ استقلالُ المراجعة — الدور " . AUDITOR_ROLE . " ════\n\n";
echo "يُنزع حقُّ الكتابة عن " . count($strip) . " شاشة (ويبقى العرضُ كاملًا):\n";
foreach ($strip as $x) {
    printf("   %-44s %s\n", $x['code'], mb_substr($x['name'], 0, 28));
}
echo "\nيبقى كما هو (حقٌّ شخصيٌّ لكل موظف): " . count($keep) . "\n";
foreach ($keep as $x) { printf("   %-44s %s\n", $x['code'], mb_substr($x['name'], 0, 28)); }

if (!$APPLY) { echo "\n(معاينةٌ — التطبيق بـ --apply)\n"; exit(0); }

$n = 0;
foreach ($strip as $x) {
    mysqli_query($conn, "UPDATE role_permissions SET can_add = 0, can_edit = 0, can_delete = 0
                          WHERE id = " . (int) $x['id']) or die('✘ ' . mysqli_error($conn) . "\n");
    $n++;
}
echo "\nطُبِّق: نُزع حقُّ الكتابة عن {$n} شاشة.\n";
$v = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) t, SUM(can_view) v, SUM(can_add + can_edit + can_delete) w
       FROM role_permissions WHERE role_id = " . AUDITOR_ROLE));
echo "الآن: {$v['t']} صفًّا · عرضٌ {$v['v']} · رايات كتابةٍ متبقية {$v['w']}\n";
