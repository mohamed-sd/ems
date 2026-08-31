<?php
/**
 * tests/gov_m114_scope_filter_proof.php — م 114 من الدستور -3
 * ═══════════════════════════════════════════════════════════════════════════
 * «الإدارةُ تملك العمليّةَ · والدورُ يحدّد من ينفّذها · والنطاقُ يحدّد أيَّ
 * السجلّاتِ يبلغها · والشاشةُ واحدةٌ مشتركةٌ تُصفّى بالنطاق — ولا تُنسخ
 * شاشةٌ لكلِّ صاحبِ نطاق».
 *
 * أربعةُ فحوص:
 *   ① المقامُ يُطبع — المساراتُ المشتركةُ بين أكثرَ من مساحةٍ (الشاشةُ الواحدةُ
 *     تُخدم عبرَ المساحاتِ لا تُنسخ لها) وكلُّ مسارٍ منها ملفٌّ حيٌّ واحد.
 *   ② صفرُ نسخةِ نطاقٍ: لا سطحانِ حيّانِ بعنوانٍ معياريٍّ واحدٍ ومالكٍ واحدٍ
 *     وملفَّينِ مختلفَين.
 *   ③ الشاهدُ الحيُّ للمادة: سجلُّ الإسنادِ الجديدُ (WH-03) شاشةٌ واحدةٌ
 *     والنطاقُ (المخزنُ) عمودُ تصفيةٍ في جدولِها لا نسخُ شاشات.
 *   ④ السالبُ بالحقن — نسخةُ نطاقٍ مِجَسٌّ تُزرع ⇒ العدّادُ يتحرّك بواحدٍ
 *     ثم تُكنس ويُثبَت كنسُها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$PROBE_ID = 'SCR-M114';
$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
/** عدُّ نسخِ النطاق: سطحانِ حيّانِ بعنوانٍ ومالكٍ **وحبّةِ كيانٍ** واحدةٍ
 *  وملفَّينِ مختلفَين — فهذه نسخةُ شاشةٍ لنطاقٍ لا سطحٌ آخرُ بحبّتِه.
 *  (تصادمُ التسميةِ بلا حبّةٍ واحدةٍ كشفُ تسميةٍ يُقيَّد في السجلِّ لا هنا —
 *  البندُ CL-PAT-DUPLABEL يحمل أزواجَه الستّة.) */
function scopeCopies(mysqli $c)
{
    $q = $c->query("SELECT COUNT(*) FROM (
            SELECT canonical_label_ar, owner_code, grain_entity
              FROM repair01_screen_registry
             WHERE on_disk = 1 AND canonical_label_ar <> '' AND grain_entity <> ''
               AND visibility_class = 'MENU_ITEM'
               AND ownership_verdict NOT IN ('RETIRE', 'LEGACY', 'TAB_CHILD')
             GROUP BY canonical_label_ar, owner_code, grain_entity
            HAVING COUNT(DISTINCT screen_file) > 1
        ) d");
    return $q ? (int) $q->fetch_row()[0] : -1;
}

register_shutdown_function(function () use ($conn, $PROBE_ID) {
    $conn->query("DELETE FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
    $q = $conn->query("SELECT COUNT(*) FROM `repair01_screen_registry` WHERE `screen_id` = '{$PROBE_ID}'");
    if ($q && (int) $q->fetch_row()[0] !== 0) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ صفَّ {$PROBE_ID} يدويًّا\n");
    }
});

echo "══ م 114 — النطاقُ يصفّي الشاشةَ الواحدةَ ولا تُنسخ شاشةٌ لكلِّ نطاق ══\n";

/* ── ① المساراتُ المشتركةُ بين المساحات ─────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM (
        SELECT route FROM nav_items WHERE active = 1 AND route <> ''
         GROUP BY route HAVING COUNT(DISTINCT role_id) > 1
    ) s");
$shared = (int) $q->fetch_row()[0];
ok($shared > 0, '① المقامُ مطبوع: مساراتٌ تخدم أكثرَ من دورٍ بالشاشةِ الواحدةِ لا بالنسخ', "{$shared} مسارًا مشتركًا");

/* ── ② صفرُ نسخةِ نطاق ──────────────────────────────────────────────────── */
$before = scopeCopies($conn);
ok($before === 0, '② لا سطحانِ حيّانِ بعنوانٍ ومالكٍ واحدَينِ وملفَّينِ مختلفَين', "العدّاد {$before}");

/* ── ③ الشاهدُ الحيُّ: WH-03 شاشةٌ واحدةٌ ونطاقُها عمودُ تصفية ──────────── */
$n = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
    WHERE canonical_label_ar = 'إسناد أمناء المخازن' AND on_disk = 1")->fetch_row()[0];
$col = (int) $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'proc_wh_custodian' AND COLUMN_NAME = 'warehouse_id'")->fetch_row()[0];
ok($n === 1 && $col === 1, '③ سجلُّ الإسناد: شاشةٌ واحدةٌ والنطاقُ (المخزن) عمودُ تصفيةٍ في حبّتِها', "سطح {$n} · عمودُ نطاقٍ {$col}");

/* ── ④ السالب: نسخةُ نطاقٍ مِجَسٌّ تُرى فورًا ───────────────────────────── */
$ok4 = $conn->query("INSERT INTO repair01_screen_registry
    (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
     lifecycle, lifecycle_rule, visibility_class, visibility_rule, on_disk, origin,
     canonical_label_ar, surface_kind, ownership_verdict, verdict_rule, verdict_at,
     grain_entity, grain_witness)
    VALUES ('{$PROBE_ID}', 'wh_custodians_site2.php', 'Procurement/wh_custodians_site2.php', 'PROBE',
     'DEP-17', 'مجس', 'PROBE-M114', 'LIVE_REGISTERED', 'PROBE', 'MENU_ITEM', 'PROBE', 1, 'BUILD',
     'إسناد أمناء المخازن', 'SOURCE', 'DOMAIN_SOURCE', 'مجسُّ اختبارٍ سالبٍ لمادة 114 — يُكنس فورًا', NOW(),
     'proc_wh_custodian', 'مجسُّ م114 — الحبّةُ نفسُها بملفٍّ ثانٍ')");
ok((bool) $ok4, '④أ المِجَسُّ زُرع (نسخةُ نطاقٍ مزعومة)', $ok4 ? '' : $conn->error);
$after = scopeCopies($conn);
ok($after === $before + 1, '④ب العدّادُ تحرّك بواحدٍ — النسخُ يُرى لا يُبتلع', "{$before} ⇒ {$after}");
$conn->query("DELETE FROM repair01_screen_registry WHERE screen_id = '{$PROBE_ID}'");
$swept = ($conn->affected_rows === 1);
ok($swept, '④ج المِجَسُّ كُنس وثبت كنسُه');
$final = scopeCopies($conn);
ok($final === 0, '④د العدّادُ عاد صفرًا', "العدّاد {$final}");

echo "\n═ النتيجة: ✔ {$pass} · ✘ {$fail} ═\n";
exit($fail === 0 ? 0 : 1);
