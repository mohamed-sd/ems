<?php
/**
 * tools/perm01_register_new_screens.php — تسجيلُ شاشاتِ جولةِ PERM-01 الخمس
 * ═══════════════════════════════════════════════════════════════════════════
 * **بناءٌ سبق تسجيلَه** ([[registry-lags-the-build]]): خمسُ شاشاتٍ بُنيت ووُضعت
 * في `nav_workspace_placements` **بحالةِ `ACTIVE` ومعرِّفٍ واسمٍ معياريٍّ
 * وإدارةٍ مالكة** — ثمَّ لم يُكتب لها صفٌّ في `repair01_screen_registry`.
 * فرسَبت ثلاثةُ حواجبَ على عطبٍ واحد: `U1` (مسارٌ مُصيَّرٌ بلا صفٍّ في
 * المصفوفة) و`RP-01` (شاشةٌ حيّةٌ بلا صفٍّ في السجل) و`RP-02` (مسارُ تنقّلٍ
 * بلا إدارةٍ مالكة).
 *
 * ⛔ **ولا تُؤلَّف هنا قيمةُ حوكمةٍ واحدة**: المعرِّفُ والاسمُ المعياريُّ
 *   والإدارةُ والمجموعةُ **تُنقل حرفًا من سجلِّ المواضعِ** — وهو الحاكمُ
 *   ([[navr-placement-model]]). والمقيسُ وحدَه ما يُضاف: `grain_entity`
 *   مشتقٌّ من الجدولِ الذي تحكمه الشاشةُ فعلًا، و`on_disk` من القرص.
 *
 * ◆ **و`db_backup` بلا كِيان**: سطحُ بنيةٍ تحتيّةٍ يقرأ ملفاتِ النسخِ لا جدولًا
 *   — فيُترك `grain_entity` فارغًا **كما في صفوفِ الإدارةِ القائمة** ولا
 *   يُخترع له جدول.
 *
 * التشغيل: php tools/perm01_register_new_screens.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("⛔ اتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* الكِيانُ الذي تحكمه كلُّ شاشةٍ — مقيسٌ من الشيفرةِ لا مخمَّن.
   ◆ **ومَن سمّى كِيانًا كتب شاهدَه**: `chk_grain_witness` في المخطَّطِ يشترط
     `grain_entity = '' OR grain_witness <> ''` — فالاسمُ بلا شاهدٍ دعوى.
     والحبّةُ (`cardinality`) من فعلِ الشاشةِ نفسِه لا من الجدول. */
$ENTITY = array(
    'governance/auth_profile_edit.php' => array('gov_role_profiles', 'ROW',
        'الجدول gov_role_profiles بمفتاحه في المخطط — والشاشة تحرر قالب دور واحد ببنوده'),
    'governance/perm_audit_log.php'    => array('gov_authority_grants', 'LIST',
        'الجدول gov_authority_grants بصفوف المنح — والشاشة تعرضها سجلا للمحاسبة ولا تكتبها'),
    'governance/break_glass_open.php'  => array('scr_break_glass', 'ROW',
        'الجدول scr_break_glass بصف الفتح الواحد — والشاشة تفتح حالة طوارئ بأثر مسجل'),
    'governance/bus_monitor.php'       => array('ems_event_deliveries', 'LIVE_READ',
        'الجدول ems_event_deliveries بحالة التسليم — والشاشة قراءة حية لمؤشرات المستهلكين'),
    /* بنيةٌ تحتيّةٌ تقرأ ملفاتِ النسخِ لا جدولًا — فلا كِيانَ ولا شاهدَ يُخترع */
    'settings/db_backup.php'           => array('', 'NONE', ''),
);
/* المسارُ على القرصِ بحالةِ حروفِه — فالسجلُّ يخزّنه كما هو */
$FILE = array(
    'governance/auth_profile_edit.php' => 'Governance/auth_profile_edit.php',
    'governance/perm_audit_log.php'    => 'Governance/perm_audit_log.php',
    'governance/break_glass_open.php'  => 'Governance/break_glass_open.php',
    'governance/bus_monitor.php'       => 'Governance/bus_monitor.php',
    'settings/db_backup.php'           => 'Settings/db_backup.php',
);

/* ═══ ① يُقرأ الحاكمُ قبلَ الكتابةِ — ولا يُكتب على مجهول ═══════════════ */
$q = $conn->query(
    "SELECT screen_id, route, canonical_label, workspace_id, group_id, status
       FROM nav_workspace_placements
      WHERE status = 'ACTIVE'
        AND (route LIKE '%auth_profile_edit%' OR route LIKE '%perm_audit_log%'
          OR route LIKE '%break_glass_open%'  OR route LIKE '%db_backup%'
          OR route LIKE '%bus_monitor%')");
if (!$q) { exit("⛔ قراءةُ المواضع: {$conn->error}\n"); }
$place = array();
while ($r = $q->fetch_assoc()) {
    $k = strtolower(rtrim($r['route'], '/'));
    if (substr($k, -4) !== '.php') { $k .= '.php'; }
    $place[$k] = $r;
}
printf("مواضعُ نشطةٌ مقروءة: %d\n", count($place));

/* الإدارةُ المالكةُ باسمِها — من سجلِّ الإدارات */
$deptAr = array();
$q = $conn->query("SELECT dept_code, dept_name_ar FROM repair01_departments");
while ($q && $r = $q->fetch_row()) { $deptAr[$r[0]] = $r[1]; }

$made = 0; $skip = 0; $miss = array();
foreach ($ENTITY as $route => $spec) {
    list($entity, $card, $witness) = $spec;
    if (!isset($place[$route])) { $miss[] = "$route — لا موضعَ نشطًا له"; continue; }
    $P   = $place[$route];
    $scr = $P['screen_id'];
    $ws  = $P['workspace_id'];
    if (!isset($FILE[$route]) || !is_file($ROOT . '/' . $FILE[$route])) {
        $miss[] = "$route — لا ملفَّ على القرص"; continue;
    }
    $ex = $conn->query("SELECT screen_id FROM repair01_screen_registry
                         WHERE screen_id = '{$e($scr)}' OR LOWER(route) = '{$e($route)}'");
    if ($ex && $ex->num_rows) { printf("  ⟳ مسجَّلٌ سلفًا: %-9s %s\n", $scr, $route); $skip++; continue; }

    printf("  + %-9s %-34s ⇐ %s · %s · كِيان=%s\n", $scr, $route,
           $ws, $P['canonical_label'], $entity !== '' ? $entity : '—');
    if (!$APPLY) { continue; }

    $sql = "INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule,
         visibility_class, visibility_rule, on_disk, origin,
         ghost_verdict, ghost_why, guard_kind, guard_evidence, w2_why, src_ref,
         updated_at, canonical_label_ar, surface_kind, ownership_verdict, action_guard,
         permission_policy, grain_ar, grain_entity, grain_cardinality, grain_measured,
         grain_rule, grain_witness, grain_multi, source_of_truth, state_model_ref,
         finance_debt_class, debt_owner, debt_wave, verdict_rule, verdict_at,
         sot_rule, sot_witness, sot_snapshot, grain_tier, grain_fact_scope)
        VALUES ('{$e($scr)}', '{$e($FILE[$route])}', '{$e($route)}', 'NAV_WORKSPACE_PLACEMENT',
         '{$e($ws)}', '{$e($deptAr[$ws] ?? '')}', 'NAV_WORKSPACE_PLACEMENT',
         'LIVE_REGISTERED', 'ACTIVE_PLACEMENT', '', '',
         'MENU_ITEM', 'NAV_WORKSPACE_PLACEMENT', 1, 'BUILD',
         '', '', 'حارس صلاحية في الملف نفسه', '', 'PERM-01 — تسجيلٌ لحق بناءً سابقًا',
         'nav_workspace_placements — الموضع النشط', NOW(),
         '{$e($P['canonical_label'])}', 'SOURCE', 'DOMAIN_SOURCE', '',
         'DOMAIN_OWNER_PLUS_GRANTED', '', '{$e($entity)}', '{$e($card)}', '',
         '', '{$e($witness)}', 0, '', '',
         '', '', '', 'PERM-01 — المعرف والاسم والادارة من سجل المواضع الحاكم', NOW(),
         '', '', '', 'NONE', 'NONE')";
    if (!$conn->query($sql)) { exit("⛔ إدراج {$scr}: {$conn->error}\n"); }
    $made++;
}
foreach ($miss as $m) { echo "  ✘ $m\n"; }
printf("\nجديدٌ=%d · قائمٌ سلفًا=%d · متعذِّرٌ=%d\n", $made, $skip, count($miss));
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }

/* ═══ ② التحقّقُ بإعادةِ القراءة — ⛔ ولا يُصدَّق الكاتبُ على كلمتِه ═══ */
$ok = 0;
foreach ($ENTITY as $route => $x) {
    $q = $conn->query("SELECT screen_id FROM repair01_screen_registry WHERE LOWER(route) = '{$e($route)}'");
    if ($q && $q->num_rows) { $ok++; }
}
printf("✔ أُعيدت القراءةُ: %d من %d مسارًا له صفٌّ في السجل\n", $ok, count($ENTITY));
exit($ok === count($ENTITY) ? 0 : 1);
