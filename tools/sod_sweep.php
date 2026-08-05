<?php
/**
 * tools/sod_sweep.php — مسحُ فصل الواجبات (E-04 RB-04 · AC-E04-04)
 * ───────────────────────────────────────────────────────────────────────────
 * «فصلُ الواجبات بنيويٌّ لا سياسة — والاكتفاءُ بسياسةٍ مكتوبةٍ يجعل الفصلَ رجاءً».
 * يكشف من يجمع طرفَي دورةٍ ممنوعة، بحسب خريطة includes/sod_map.php والمصدرِ
 * الحي (role_permissions). قرار المالك: **يكشف الآن ولا يمنع**؛ والمنعُ بعد
 * أن ترى القائمة. والأزواجُ التقريبيةُ تُعلَن تقريبيةً ولا تُمنع أبدًا.
 *
 * php tools/sod_sweep.php --map     خريطةُ الأربعةِ والعشرين رمزًا للمراجعة
 * php tools/sod_sweep.php           المسح: الأدوارُ والحساباتُ الجامعة
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/sod_map.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$SHOW_MAP = in_array('--map', $argv, true);

$GRADE_AR = array('exact' => 'دقيقة', 'approx' => 'تقريبية', 'absent' => 'بلا شاشة');

/* ── الخريطة للمراجعة ─────────────────────────────────────────────────── */
if ($SHOW_MAP) {
    echo "════ خريطةُ فصل الواجبات — أربعةٌ وعشرون رمزًا ════\n\n";
    printf("%-28s %-42s %-9s %-10s\n", 'الرمز المعنوي', 'الشاشة الحية', 'الراية', 'الدقة');
    echo str_repeat('─', 96) . "\n";
    $g = array('exact' => 0, 'approx' => 0, 'absent' => 0);
    foreach (ems_sod_map() as $code => $t) {
        $g[$t['grade']]++;
        printf("%-28s %-42s %-9s %-10s%s\n", $code,
            $t['screen'] ?? '—', $t['action'] ?? '—', $GRADE_AR[$t['grade']],
            $t['note'] !== '' ? '  · ' . $t['note'] : '');
    }
    echo "\nالحصيلة: دقيقة {$g['exact']} · تقريبية {$g['approx']} · بلا شاشة {$g['absent']}\n";
    exit(0);
}

/* ── المسح ────────────────────────────────────────────────────────────── */
$pairs = array();
$r = mysqli_query($conn, "SELECT sod_id, conflict_code, name_ar, permission_a, permission_b, severity,
                                 compensating_control FROM sod_conflicts WHERE active = 1 ORDER BY sod_id");
while ($x = mysqli_fetch_assoc($r)) { $pairs[] = $x; }

$roles = array();
$r = mysqli_query($conn, "SELECT id, name FROM roles ORDER BY id");
while ($x = mysqli_fetch_assoc($r)) { $roles[(int) $x['id']] = $x['name']; }

$users = array();
$r = mysqli_query($conn, "SELECT id, username, role FROM users WHERE status='active' AND role <> '-1' ORDER BY id");
while ($x = mysqli_fetch_assoc($r)) { $users[] = $x; }

echo "════ مسحُ فصل الواجبات ════\n";
echo "الأزواج: " . count($pairs) . " · الأدوار: " . count($roles) . " · الحسابات النشطة: " . count($users) . "\n\n";

$hits = array();   // sod_id => [roleId => [held...]]
$gradeOf = array();
foreach ($pairs as $p) {
    $A = ems_sod_split_codes($p['permission_a']);
    $B = ems_sod_split_codes($p['permission_b']);
    $grade = ems_sod_pair_grade(array_merge($A, $B));
    $gradeOf[$p['sod_id']] = $grade;
    if ($grade === 'absent') { continue; }
    foreach ($roles as $rid => $rname) {
        $held = ems_sod_codes_of_role($conn, $rid);
        $hasA = true; foreach ($A as $c) { if (empty($held[$c])) { $hasA = false; break; } }
        if (!$hasA) { continue; }
        $hasB = true; foreach ($B as $c) { if (empty($held[$c])) { $hasB = false; break; } }
        if ($hasB) { $hits[$p['sod_id']][$rid] = array_merge($A, $B); }
    }
}

foreach ($pairs as $p) {
    $sid = $p['sod_id'];
    $grade = $gradeOf[$sid];
    $mark = ($grade === 'absent') ? '⚪' : (empty($hits[$sid]) ? '✔' : '⛔');
    printf("%s %d · %-22s [%s · %s]\n   %s\n", $mark, $sid, $p['conflict_code'],
        $p['severity'], $GRADE_AR[$grade], $p['name_ar']);
    if ($grade === 'absent') {
        echo "   لا يُقاس — رمزٌ بلا شاشةٍ في السجل (لا يُدَّعى قياسُه)\n\n";
        continue;
    }
    if (empty($hits[$sid])) { echo "   صفرُ دورٍ يجمع الطرفين\n\n"; continue; }
    foreach ($hits[$sid] as $rid => $codes) {
        $un = array();
        foreach ($users as $u) { if ((int) $u['role'] === $rid) { $un[] = $u['username']; } }
        printf("   ⛔ دور %d «%s» — %d حسابًا: %s\n", $rid, $roles[$rid], count($un),
            $un ? implode(' · ', array_slice($un, 0, 6)) : 'لا حساب');
    }
    echo "   الضابطُ التعويضي: " . ($p['compensating_control'] ?: '—') . "\n\n";
}

$measurable = 0; $conflicted = 0; $absent = 0; $accounts = 0;
foreach ($pairs as $p) {
    $sid = $p['sod_id'];
    if ($gradeOf[$sid] === 'absent') { $absent++; continue; }
    $measurable++;
    if (!empty($hits[$sid])) {
        $conflicted++;
        foreach ($hits[$sid] as $rid => $_) {
            foreach ($users as $u) { if ((int) $u['role'] === $rid) { $accounts++; } }
        }
    }
}
echo "════ الحكم ════\n";
echo "  أزواجٌ قابلةٌ للقياس: {$measurable} من " . count($pairs) . " (وبلا شاشة: {$absent})\n";
echo "  أزواجٌ فيها تعارضٌ فعلي: {$conflicted}\n";
echo "  حساباتٌ تحمل دورًا جامعًا: {$accounts}\n";
echo "  الوضع: كشفٌ فقط — لا منعَ (قرار المالك: المسحُ ثم المنع)\n";
