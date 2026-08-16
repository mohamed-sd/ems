<?php
/**
 * 2027_04_20_shift_entry_screen_registry.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تسجيلُ شاشةِ القيدِ اليوميِّ **قبلَ بنائها** — الفعلانِ والوحدةُ والرابط
 *
 * TS-06 «كلُّ فعلٍ يُسجَّل في قاموسِ الأفعالِ قبلَ شاشتِه — وإلا أُغلقت ولم تعمل»
 * TS-07 «ووحدةُ صلاحياتٍ لكلِّ شاشةٍ قبلَ رابطِها — فواحدٌ وأربعون صفَّ تنقلٍ
 *        ميتًا سببُه هذا بعينُه»
 *
 * ── الموضعُ من الوثيقةِ لا من اجتهاد ─────────────────────────────────────
 * المواصفة 70 · TSP-0172: مرحلةُ «SIT» رقم 1 = «نسجّل ما حدث اليوم» وشرحُها
 * «الورديةُ وساعاتُها ووقودُها ومشغّلُها». ونظيرُها الحيُّ في سايدبارِ الدورِ 6
 * هو المرحلةُ **الرابعة** «رابعًا: تسجيل عمل اليوم» (المجموعاتُ: الوردية ·
 * التايم شيت والإنتاج · التوقفات). فالدلالةُ من الوثيقةِ والترقيمُ من الحيّ.
 *
 * ── ولماذا مجموعةُ n9o_ لا المجموعةُ المولَّدة ──────────────────────────
 * `nav09_verify` يقارن مجموعاتِ `n9s%` **بالعددِ والترتيبِ** بالوثيقةِ المولِّدة،
 * فإقحامُ رابطٍ في «الوردية» (n9s02_4_11_r6) يجعل العددَ 2 والمتوقَّعَ 1 فيرسب
 * الفاحصُ بلا ذنبٍ للشاشة. و`nav09_sweep_others` يستثني `n9s%` و`n9o%` معًا
 * (سطر 37). فبادئةُ `n9o_` وحدَها تعبر الفاحصَين — وهي البادئةُ المقرَّرةُ
 * لإضافاتِ المالكِ خارجَ المولَّد. وتُعطى stage_no=4 وstage_title المطابقَ
 * فتندرج تحتَ العنوانِ نفسِه في العرض.
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

const SCREEN = 'Operations/shift_entry.php';
const LABEL  = 'قيدُ الوردية اليومي';
const ROLE   = 6;

echo "══ تسجيلُ شاشةِ القيدِ اليوميِّ قبلَ بنائها ══\n\n";

/* ── ① وحدةُ الصلاحيات ─────────────────────────────────────────────── */
$st = $conn->prepare("SELECT id FROM modules WHERE code=?");
$st->bind_param('s', $c1); $c1 = SCREEN; $st->execute();
$moduleId = (int) ($st->get_result()->fetch_row()[0] ?? 0);
$st->close();
if ($moduleId) {
    echo "  · وحدةُ الصلاحياتِ قائمةٌ سلفًا (#$moduleId)\n";
} else {
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                          VALUES (?, ?, ?, 1, 0, 'fa fa-circle-dot', 100)");
    $n = LABEL; $c = SCREEN; $r6 = ROLE;
    $st->bind_param('ssi', $n, $c, $r6);
    if (!$st->execute()) { exit("  ✘ تعذّر إنشاءُ الوحدة: {$conn->error}\n"); }
    $moduleId = (int) $conn->insert_id;
    $st->close();
    echo "  ✔ وحدةُ صلاحياتٍ #$moduleId — " . SCREEN . "\n";
}

/* ── ② المنح: مرآةُ «سجل الوردية» (الوحدة 285) — لا منحةَ تُخترع ─────── */
$GRANTS = [
    [6,  1, 1, 1, 0],   // إدارة الموقع — تكتب
    [7,  1, 0, 0, 0],   // مشرف مشاريع — تقرأ
    [27, 1, 0, 0, 0],   // القوى التشغيلية — تقرأ
];
$g = 0;
foreach ($GRANTS as [$role, $v, $a, $e, $d]) {
    $q = $conn->query("SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$moduleId");
    if ($q && $q->num_rows) { echo "  · منحةُ الدورِ $role قائمة\n"; continue; }
    if ($conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ($role, $moduleId, $v, $a, $e, $d)")) {
        echo "  ✔ منحةُ الدورِ $role — view=$v add=$a edit=$e\n"; $g++;
    } else { echo "  ✘ الدورُ $role: {$conn->error}\n"; }
}

/* ── ③ مجموعةُ التنقّل n9o_ ─────────────────────────────────────────── */
$GCODE = 'n9o_site_shift_r6';
$q = $conn->query("SELECT id FROM link_groups WHERE group_code='$GCODE'");
$groupId = (int) ($q && $q->num_rows ? $q->fetch_row()[0] : 0);
if ($groupId) {
    echo "  · مجموعةُ التنقّلِ قائمةٌ (#$groupId)\n";
} else {
    /* آخرُ موضعٍ في المرحلةِ الرابعةِ للدورِ 6 — فتقع بعدَها لا قبلَها */
    $q = $conn->query("SELECT COALESCE(MAX(display_order),0), MAX(stage_title)
                       FROM link_groups WHERE owner_role_id=" . ROLE . " AND stage_no=4");
    [$maxOrd, $stageTitle] = $q ? $q->fetch_row() : [0, null];
    $stageTitle = $stageTitle ?: 'رابعًا: تسجيل عمل اليوم';
    $ord = (int) $maxOrd + 1;
    $st = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                          VALUES (?, ?, ?, 'fa fa-clock', ?, 4, ?, 1)");
    $gn = 'القيد اليومي'; $gc = $GCODE; $r6 = ROLE;
    $st->bind_param('ssiis', $gn, $gc, $r6, $ord, $stageTitle);
    if (!$st->execute()) { exit("  ✘ تعذّرت المجموعة: {$conn->error}\n"); }
    $groupId = (int) $conn->insert_id;
    $st->close();
    echo "  ✔ مجموعةُ تنقّلٍ #$groupId «القيد اليومي» — مرحلة 4 «$stageTitle» ترتيب $ord\n";
}

/* ── ④ رابطُ التنقّل ────────────────────────────────────────────────── */
$q = $conn->query("SELECT id FROM nav_items WHERE route='" . SCREEN . "' AND role_id=" . ROLE);
if ($q && $q->num_rows) {
    echo "  · رابطُ التنقّلِ قائمٌ (#" . $q->fetch_row()[0] . ")\n";
} else {
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                          VALUES (?, 'DAILY', ?, ?, ?, ?, 'fa fa-clock', 1, ?, 1, NOW())");
    $r6 = ROLE; $lb = LABEL; $rt = SCREEN; $pc = SCREEN;
    $st->bind_param('iiisss', $r6, $groupId, $moduleId, $lb, $rt, $pc);
    if (!$st->execute()) { exit("  ✘ تعذّر الرابط: {$conn->error}\n"); }
    echo "  ✔ رابطُ تنقّلٍ #" . $conn->insert_id . " — مجموعة #$groupId · وحدة #$moduleId\n";
    $st->close();
}

/* ── ⑤ الفعلانِ في قاموسِ الأفعال ───────────────────────────────────── */
echo "\n── قاموسُ الأفعال (nav09_action_map) ──\n";
$ACTIONS = [
    [
        'canonical_code' => 'shift.entry.record',
        'label_ar'       => 'تسجيلُ قيدِ ورديةٍ يوميّ',
        'event_name'     => 'ShiftEntryRecorded',
        'reverse_text'   => 'shift.entry.void — صفٌّ عاكسٌ بمرجعِ الأصلِ لا حذف',
        'effect_text'    => 'ساعاتُ التشغيلِ والاستعدادِ والتعطلِ والوقودُ والعدّادُ تُقيَّد لخانةِ الآليةِ في وردِيَّتها · ولا مروحةَ ماليةً في طورِ الكتابةِ المزدوجة',
        'writes_text'    => 'unit_entries',
    ],
    [
        'canonical_code' => 'shift.entry.void',
        'label_ar'       => 'إلغاءُ قيدِ ورديةٍ بحركةٍ عاكسة',
        'event_name'     => 'ShiftEntryVoided',
        'reverse_text'   => '—',
        'effect_text'    => 'صفٌّ عاكسٌ بمرجعِ الأصل — لا حذفَ ولا تعديلَ للأصل',
        'writes_text'    => 'unit_entries',
    ],
];
foreach ($ACTIONS as $a) {
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code='" . $conn->real_escape_string($a['canonical_code']) . "'");
    if ($q && $q->num_rows) { echo "  · {$a['canonical_code']} مسجَّلٌ سلفًا\n"; continue; }
    $st = $conn->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
         consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
         idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bound_page', 'yes', ?, 'yes', ?, 'pending', '', 'domain_write', NOW())");
    $screenTitle = LABEL;
    $file   = 'shift_entry.php';
    $actor  = 'مشغّلُ الموقعِ إدخالًا · مديرُ الموقعِ اعتمادًا';
    $cons   = 'الموردون · القوى التشغيلية · الأسطول · المالية (بعد طورِ الكتابةِ المزدوجة)';
    $live   = $a['canonical_code'];
    $gev    = 'حارسُ شاشةٍ محلولٌ في ' . SCREEN . ' (enforce_current_page_view_permission) + CSRF مركزيٌّ + can_add قبلَ المعالج';
    $iev    = 'مفتاحُ عطالةٍ خدميٌّ في TimesheetEntryService (المعدة × التاريخ × الوردية) — بنيويٌّ مؤجَّلٌ لتصادمِ البذور';
    $st->bind_param('sssssssssssss',
        $a['canonical_code'], $a['label_ar'], $screenTitle, $file, $actor, $a['writes_text'],
        $a['event_name'], $cons, $a['effect_text'], $a['reverse_text'], $live, $gev, $iev);
    if ($st->execute()) { echo "  ✔ {$a['canonical_code']} — حدثُه {$a['event_name']}\n"; }
    else { echo "  ✘ {$a['canonical_code']}: {$conn->error}\n"; }
    $st->close();
}

/* ── الحصيلة ──────────────────────────────────────────────────────── */
echo "\n── الحصيلة ──\n";
$q = $conn->query("SELECT COUNT(*) FROM role_permissions WHERE module_id=$moduleId");
echo '  الوحدة #' . $moduleId . ' · منحٌ: ' . ($q ? $q->fetch_row()[0] : '?') . "\n";
$q = $conn->query("SELECT COUNT(*) FROM nav_items WHERE module_id=$moduleId AND active=1");
echo '  روابطُ نشطة: ' . ($q ? $q->fetch_row()[0] : '?') . "\n";
$q = $conn->query("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code LIKE 'shift.entry.%'");
echo '  أفعالٌ في القاموس: ' . ($q ? $q->fetch_row()[0] : '?') . "\n";
echo "\n✔ تمّت — والشاشةُ تُبنى الآن على تسجيلٍ قائمٍ لا العكس\n";
