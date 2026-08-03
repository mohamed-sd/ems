<?php
/**
 * tools/nav09_apply_owner.php — مُطبِّقُ قرار المالك على المعلَّقات (NAV-09)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ مصنّفَ «دليل السايدبار وقرار المالك» بعد أن يملأ المالكُ أعمدتَه:
 *   ورقة «قرار المالك»  : ① القرار (نقل/دمج/حذف) · ② التبويب المقصود · ③ يُدمج مع
 *   ورقة «شاشات قريبًا» : قرار المالك (إبقاء/حذف/أولوية…)
 *
 * الأفعال:
 *   نقل/إبقاء → الرابطُ ينتقل من «أخرى» إلى مجموعة «— بقرار المالك» داخل
 *               التبويب (المرحلة) المقصود، بكوده n9o فلا يمسّه التوليدُ ولا
 *               يُحاسَب على أمانة الوثيقة (خارجها بقرارٍ معلن).
 *   دمج       → يُبطل الرابطُ ويُفتح تحويلٌ بعدّادٍ إلى وجهة الدمج (SCR-01 §5).
 *   حذف/تجميد → يُبطل ويُدوَّن في سجل المجمَّد بقراره وتاريخه.
 *   قريبًا-حذف → يُبطل رابطُ «قريبًا» ويُعلَّم قانونيُّه owner-hide فلا يعيده
 *               التوليد (المستورد يحترم العلامة).
 *
 * التشغيل:
 *   php tools/nav09_apply_owner.php --file="مسار الملف المعاد"            (معاينة)
 *   php tools/nav09_apply_owner.php --file="…" --apply                    (تنفيذ)
 *   php tools/nav09_apply_owner.php --cleanup                             (حذف «أخرى» الفارغة)
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$CLEANUP = in_array('--cleanup', $argv, true);
$file = null;
foreach ($argv as $a) { if (strpos($a, '--file=') === 0) { $file = substr($a, 7); } }

/* اسمُ الإدارة في المصنّف → رقمُ الدور (كما في أوراقه نصًّا) */
$DEPT_ROLE = array(
    'ادارة التشغيل' => 1, 'ادارة الموردين' => 2, 'ادارة الاسطول' => 3,
    'ادارة الموارد البشرية' => 4, 'إدارة الموقع (قديم — مدمج في 6)' => 5,
    'إدارة الموقع' => 6, 'الإدارة التنفيذية' => 9, 'ادارة المبيعات' => 12,
    'ادارة الصيانة' => 13, 'إدارة الصلاحيات' => 15, 'إدارة المشتريات' => 16,
    'إدارة المالية' => 17, 'إدارة النقل والترحيل' => 23, 'إدارة البلاغات' => 24,
    'أمين المستودع' => 25, 'إدارة التمويل' => 26, 'القوى التشغيلية' => 27,
);

$norm = function ($s) { return preg_replace('/\s+/u', ' ', trim((string) $s)); };
$verbOf = function ($d) use ($norm) {
    $d = $norm($d);
    if ($d === '' || $d === '—') { return null; }
    if (mb_strpos($d, 'دمج') !== false) { return 'merge'; }
    if (mb_strpos($d, 'حذف') !== false || mb_strpos($d, 'تجميد') !== false
        || mb_strpos($d, 'إزالة') !== false || mb_strpos($d, 'ازالة') !== false) { return 'retire'; }
    if (mb_strpos($d, 'نقل') !== false || mb_strpos($d, 'إبقاء') !== false
        || mb_strpos($d, 'ابقاء') !== false || mb_strpos($d, 'يبقى') !== false) { return 'move'; }
    return 'unknown';
};

/* «أخرى» الفارغة تُحذف — خطوةُ الختام */
if ($CLEANUP) {
    $r = mysqli_query($conn, "SELECT lg.id, lg.owner_role_id,
            (SELECT COUNT(*) FROM nav_items ni WHERE ni.group_id = lg.id AND ni.active = 1) c
            FROM link_groups lg WHERE lg.group_code LIKE 'n9s99_others%'");
    $kept = 0; $dropped = 0;
    while ($g = mysqli_fetch_assoc($r)) {
        if (intval($g['c']) > 0) { $kept++; echo "دور {$g['owner_role_id']}: «أخرى» فيها {$g['c']} — لا تُحذف قبل حسمها\n"; continue; }
        mysqli_query($conn, "DELETE FROM nav_items WHERE group_id = {$g['id']}"); // المُبطَل المتبقي
        mysqli_query($conn, "DELETE FROM link_groups WHERE id = {$g['id']}");
        $dropped++;
    }
    echo "حُذفت مجموعاتُ «أخرى» الفارغة: $dropped · بقيت (فيها معلَّق): $kept\n";
    exit($kept === 0 ? 0 : 1);
}

if (!$file || !is_file($file)) { fwrite(STDERR, "مرّر --file=مسار المصنف المعاد من المالك\n"); exit(2); }

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$wb = $reader->load($file);

/* مجموعاتُ الدور المولَّدة: stage_title → (stage_no) · وموضعُ مجموعة القرار */
$stageOfTitle = array(); // role → normalized stage_title → stage_no
$r = mysqli_query($conn, "SELECT owner_role_id, stage_no, stage_title FROM link_groups
                          WHERE group_code LIKE 'n9s%' AND group_code NOT LIKE 'n9s99%' AND stage_title IS NOT NULL");
while ($x = mysqli_fetch_assoc($r)) {
    $stageOfTitle[intval($x['owner_role_id'])][$norm($x['stage_title'])] = intval($x['stage_no']);
}
$ownerGroup = function ($role, $stageNo, $stageTitle) use ($conn) {
    $code = "n9o_{$role}_{$stageNo}";
    $r = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code = '$code'");
    if ($r && ($g = mysqli_fetch_row($r))) { return intval($g[0]); }
    $et = mysqli_real_escape_string($conn, $stageTitle);
    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
        VALUES ('— بقرار المالك', '$code', $role, 'fa fa-user-check', " . ($stageNo * 100 + 90) . ", $stageNo, '$et', 1)")
        or die('✘ ' . mysqli_error($conn) . "\n");
    return mysqli_insert_id($conn);
};

$stats = array('move' => 0, 'merge' => 0, 'retire' => 0, 'hide_soon' => 0, 'blank' => 0, 'unknown' => 0, 'notfound' => 0, 'badtab' => 0);
$problems = array(); $retiredLog = array();

/* ── ورقة قرار المالك ─────────────────────────────────────────────────── */
$s = $wb->getSheetByName('قرار المالك');
foreach ($s->toArray(null, true, false, false) as $i => $row) {
    if ($i < 3 || !ctype_digit($norm($row[0] ?? ''))) { continue; }
    $dept = $norm($row[1]); $label = $norm($row[2]); $route = $norm($row[3]);
    $decision = $row[7] ?? ''; $tab = $norm($row[8] ?? ''); $mergeTo = $norm($row[9] ?? '');
    $verb = $verbOf($decision);
    if ($verb === null) { $stats['blank']++; continue; }
    if ($verb === 'unknown') { $stats['unknown']++; $problems[] = "قرارٌ غير مفهوم «{$norm($decision)}» — $label"; continue; }
    $role = $DEPT_ROLE[$dept] ?? null;
    if ($role === null) { $stats['notfound']++; $problems[] = "إدارةٌ غير معروفة «$dept» — $label"; continue; }
    $er = mysqli_real_escape_string($conn, $route);
    $r2 = mysqli_query($conn, "SELECT ni.id FROM nav_items ni WHERE ni.role_id = $role AND ni.route = '$er' LIMIT 1");
    $item = $r2 ? mysqli_fetch_row($r2) : null;
    if (!$item) { $stats['notfound']++; $problems[] = "لا رابطَ للدور $role بمسار $route — $label"; continue; }
    $niId = intval($item[0]);

    if ($verb === 'move') {
        $stageNo = $stageOfTitle[$role][$tab] ?? null;
        if ($stageNo === null) { // مطابقة مرنة: احتواء
            foreach (($stageOfTitle[$role] ?? array()) as $t => $n) {
                if ($tab !== '' && (mb_strpos($t, $tab) !== false || mb_strpos($tab, $t) !== false)) { $stageNo = $n; $tab = $t; break; }
            }
        }
        if ($stageNo === null) { $stats['badtab']++; $problems[] = "تبويبٌ غيرُ موجودٍ للدور $role: «$tab» — $label"; continue; }
        $stats['move']++;
        if ($APPLY) {
            $gid = $ownerGroup($role, $stageNo, $tab);
            mysqli_query($conn, "UPDATE nav_items SET group_id = $gid, door = 'DAILY', active = 1, sort_order = 50 WHERE id = $niId")
                or die('✘ ' . mysqli_error($conn) . "\n");
        }
    } elseif ($verb === 'merge') {
        /* وجهةُ الدمج: مسارٌ صريحٌ أو اسمُ رابطٍ حيٍّ للدور نفسه */
        $target = null;
        if (mb_strpos($mergeTo, '.php') !== false) { $target = $mergeTo; }
        else {
            $el = mysqli_real_escape_string($conn, $mergeTo);
            $r3 = mysqli_query($conn, "SELECT route FROM nav_items WHERE role_id = $role AND active = 1 AND label_ar = '$el' LIMIT 1");
            if ($r3 && ($t = mysqli_fetch_row($r3))) { $target = preg_replace('/#.*/', '', $t[0]); }
        }
        if (!$target) { $stats['badtab']++; $problems[] = "وجهةُ دمجٍ مجهولة «$mergeTo» — $label"; continue; }
        $stats['merge']++;
        if ($APPLY) {
            mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id = $niId");
            $eo = mysqli_real_escape_string($conn, preg_replace('/#.*/', '', $route));
            $en = mysqli_real_escape_string($conn, $target);
            mysqli_query($conn, "INSERT INTO nav_redirects (old_route, new_route, active, hits)
                                 VALUES ('$eo', '$en', 1, 0)
                                 ON DUPLICATE KEY UPDATE new_route = '$en', active = 1");
        }
    } else { // retire
        $stats['retire']++;
        $retiredLog[] = array($dept, $label, $route, $norm($row[10] ?? ''));
        if ($APPLY) { mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id = $niId"); }
    }
}

/* ── ورقة شاشات قريبًا ────────────────────────────────────────────────── */
$s = $wb->getSheetByName('شاشات قريبًا');
if ($s) {
    foreach ($s->toArray(null, true, false, false) as $i => $row) {
        if ($i < 3 || !ctype_digit($norm($row[0] ?? ''))) { continue; }
        $verb = $verbOf($row[4] ?? '');
        if ($verb !== 'retire') { continue; } // الافتراضُ الإبقاءُ حتى تُبنى
        $label = $norm($row[3]); $dept = $norm($row[1]);
        $role = $DEPT_ROLE[$dept] ?? null;
        if ($role === null) { continue; }
        $el = mysqli_real_escape_string($conn, $label);
        $r2 = mysqli_query($conn, "SELECT ni.id, ni.route FROM nav_items ni
                                   WHERE ni.role_id = $role AND ni.active = 1 AND ni.label_ar = '$el'
                                     AND ni.route LIKE 'main/soon.php%' LIMIT 1");
        if (!$r2 || !($it = mysqli_fetch_assoc($r2))) { continue; }
        $stats['hide_soon']++;
        if ($APPLY) {
            mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id = " . intval($it['id']));
            if (preg_match('/screen=([a-z0-9_.]+)/', $it['route'], $m)) {
                $ec = mysqli_real_escape_string($conn, $m[1]);
                mysqli_query($conn, "UPDATE nav09_file_map SET note = 'owner-hide' WHERE canonical_file = '$ec'");
            }
        }
    }
}

/* ── التقرير ─────────────────────────────────────────────────────────── */
echo ($APPLY ? "═ نُفِّذ ═" : "═ معاينةٌ — لا تنفيذ ═") . "\n";
echo "نقلٌ إلى تبويب: {$stats['move']} · دمجٌ بتحويل: {$stats['merge']} · حذف/تجميد: {$stats['retire']} · إخفاءُ قريبًا: {$stats['hide_soon']}\n";
echo "بلا قرار: {$stats['blank']} · غيرُ مفهوم: {$stats['unknown']} · تعذّر إيجاده: {$stats['notfound']} · تبويب/وجهة خاطئة: {$stats['badtab']}\n";
foreach (array_slice($problems, 0, 20) as $p) { echo "  ⚠ $p\n"; }
if ($APPLY && $retiredLog) {
    $f = fopen(__DIR__ . '/../docs/NAV09_OWNER_RETIRED_ar.md', 'a');
    fwrite($f, "\n## دفعة " . date('Y-m-d H:i') . "\n| الإدارة | الشاشة | المسار | ملاحظة المالك |\n|---|---|---|---|\n");
    foreach ($retiredLog as $l) { fwrite($f, "| {$l[0]} | {$l[1]} | `{$l[2]}` | {$l[3]} |\n"); }
    fclose($f);
    echo "سجلُّ المجمَّد: docs/NAV09_OWNER_RETIRED_ar.md (+" . count($retiredLog) . ")\n";
}
if (!$APPLY) { echo "\nللتنفيذ أعد التشغيل بـ --apply ثم اختم بـ --cleanup لحذف «أخرى» الفارغة\n"; }
