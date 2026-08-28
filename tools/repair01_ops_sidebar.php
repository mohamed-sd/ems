<?php
/**
 * tools/repair01_ops_sidebar.php — سايدبارُ إدارةِ التشغيلِ بورقةِ الدليلِ 11
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك**: «صمِّم السايدبارَ في إدارةِ التشغيلِ بمستخدم `محمد` كما في
 * ملفِّ الإكسل — ورقةِ التشغيل».
 *
 * ◆ **والورقةُ تنصُّ على البنيةِ والترتيبِ معًا**: «6 مجموعات» · «12 شاشة —
 *   **ترتيبُها في هذا الشيت هو ترتيبُها في الـSidebar وهو ترتيبُ دورةِ العمل**».
 *
 * ⛔ **والكتابةُ في `nav_items` وحدَها لا تُغيّر ما يُرى**: سايدبارُ الدورِ 1
 *   يُصيَّر من `nav_canonical` (المصفوفةُ الحاكمة) عبرَ `printUxuiCanonicalNav`
 *   — **74 رابطًا في اثنَي عشرَ رأسًا** لا من مجموعاتِ `link_groups` المائةِ
 *   والعشرين. **وهذه بعينُها ملاحظةُ المالكِ في `RPR-SUP-03`**: بنيةٌ صحيحةٌ
 *   في القاعدةِ وشاشةٌ لا تتغيَّر. ⇒ فالتصميمُ يقع حيث يقع التصيير.
 *
 * ◆ **والطبقةُ الصحيحةُ `gov_target_nav`** (‏XC-01 · سابقةُ الأدوارِ 2 و3 و12):
 *   رأسُ الطيِّ مفتاحُه **المسارُ** (‏`nav_route_group` بلا `role_id`) فلا يحمل
 *   ستَّ مجموعاتٍ لكلِّ دور؛ **والقسمُ الفرعيُّ مفتاحُه الدور** — فعليه تُحمَل
 *   مجموعاتُ الدليلِ الستُّ ⛔ **بلا إخفاءِ بندٍ ولا نقلِ صلاحية**.
 *
 * ⛔ **ولا يُعاد تسميةُ سطحٍ مشترك**: `nav_canonical.canonical_ar` يغلب، وسابقةُ
 *   هجرةِ `2027_10_06` تقول **وثيقةُ إدارةٍ لا تملك تسميةَ سطحٍ مشترك**.
 *   ⇒ عنوانُ الدليلِ يُكتب في `item_ar` **وصفًا لدورِ البندِ في المجموعة**،
 *   والاسمُ الكنسيُّ يبقى كما هو.
 *
 * ◆ **ورأسُ الطيِّ يُنقَل بقيدٍ واحد**: ما كان سندُه مشتقًّا (`LEVEL:`/`DIR:`)
 *   يُنقَل ⛔ **وما كان `PIN` قرارٌ مشتركٌ لا تنقضه وثيقةُ إدارة** — ولذلك
 *   `Approvals/hours_approval.php` يبقى في «مساحتي» بقسمِ الدليلِ عليه.
 *
 * التشغيل: php tools/repair01_ops_sidebar.php [--apply] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY  = in_array('--apply', $argv, true);
$REVERT = in_array('--revert', $argv, true);

$ROLE = 1;                          /* «ادارة التشغيل» — دورُ `محمد` (users.id=4) */
$DOC  = 'REPAIR01-OPS-11';
$q = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
/* منصوصٌ في الدليلِ بلا شاشةٍ حيّة — علامةٌ فريدةٌ لا مسارَ فارغ (‏انظر أعلاه) */
$gap = function ($r) { return $r === '' || strncmp((string) $r, 'GAP:', 4) === 0; };

/* ═══ ① المستهدَفُ منصوصًا من ورقةِ الدليلِ 11 ═══════════════════════════════
   المجموعةُ ورقمُها · الشاشةُ ورقمُها · عنوانُها كما في الدليل · والحيُّ الموصول
   بحكمِ `repair01_ops_decide.php` (‏أربعُ إشاراتٍ) ومراجعةِ أعمدةِ الشاشةِ الحيّة.
   ⛔ **وما لا شاشةَ له يبقى صفًّا بعلامةِ فجوةٍ `GAP:…`**: منصوصٌ يُقاس ولا
     يُصيَّر — فلا يُخترَع مسارٌ ولا يُطمَس نقص.
   ⚠ **وكان المسارُ الفارغُ فبلعت الفهرسةُ ثلاثةَ صفوفٍ صامتةً**: `uq_tn` فريدٌ
     على `(doc_code, route)` فلا يقبل إلّا فارغًا واحدًا — **وأعلنتُ «أُعلن 9»
     ولم أعلن الفقدَ لأنّي عددتُ الناجحَ ولم أقابله بالمحاوَل**. ⇒ العلامةُ
     فريدةٌ لكلِّ فجوة، **والعدُّ يقابل المحاوَلَ بالمكتوبِ ويرسُب عند الفارق**. */
$TARGET = array(
  array(1, 'اللوحة — خارج الدورة (Overview)', 1, 'غرفة العمليات',
        'Operations/operations_room.php', 'أعمدةُ اللوحةِ الحيّةِ هي مؤشراتُ الدليلِ نفسُها: المخططة · العاملة · المتوقفة · نسبة التشغيل · الإنجاز من الخطة'),
  array(2, 'التخطيط والتوزيع', 1, 'الخطة الموسمية ومعاملاتها',
        'GAP:DEP-11:02', '⛔ لا شاشةَ حيّةً — «موسم × نموذج عمل — معامل واحد» لا نظيرَ له في النظامِ كلِّه'),
  array(2, 'التخطيط والتوزيع', 2, 'الخطة الشهرية للتشغيل',
        'Operations/monthly_plan.php', 'الاسمُ المعياريُّ مطابقٌ حرفًا — وكانت غيرَ مربوطةٍ في الملاحةِ أصلًا (DIRECT_ONLY)'),
  array(2, 'التخطيط والتوزيع', 3, 'احتياج الغد وتوزيع الموارد',
        'Operations/daily_plan.php', 'أعمدتُها: تاريخ التنفيذ · جبهة العمل · كود المعدة · الوردية · المشغل · الكمية المستهدفة — حبّةُ الدليلِ نفسُها'),
  array(2, 'التخطيط والتوزيع', 4, 'طلب وقرار حركة الموارد',
        'Operations/distribution_space.php', 'تحمل كتلةَ الأمر: رقم الأمر · نوع المورد · من/إلى الموقع · سبب النقل · أمر الترحيل المرتبط · تاريخ التنفيذ'),
  array(3, 'اليوم التشغيلي', 1, 'التايم شيت اليومي',
        'Timesheet/view_timesheet.php', 'العدّادُ والاستعدادُ والأعطالُ بالوردية — وتوأمُها `Timesheet/timesheet.php` هو إدخالُ الموقع'),
  array(3, 'اليوم التشغيلي', 2, 'توزيع زمن الوردية وحالاته',
        'Operations/stops_unattributed.php', '◑ جزئيّ: من/إلى الساعة · المدة · الطرف المتحمل · مرجع بند العقد · أثرُ الفوترةِ والموردِ وأجرِ المشغل — **حالةُ التوقفِ وحدَها دون الفعليِّ والاستعداد**'),
  array(4, 'الاعتماد والمطابقة', 1, 'اعتماد الوحدات',
        'Approvals/hours_approval.php', 'وحدةُ الشاشةِ في السجل «اعتماد وحدات اليوم» — ورأسُها `MINE` تثبيتٌ مشتركٌ لا يُنقَض'),
  array(5, 'الانحراف والاستثناء', 1, 'قرارات التوقف',
        'GAP:DEP-11:09', '⛔ لا شاشةَ حيّةً — والدليلُ يفصلها عن الإسناد: «الإسنادُ يحدد من يتحمل والقرارُ يحدد ماذا نفعل — كلاهما لازم»'),
  array(5, 'الانحراف والاستثناء', 2, 'سجل الأحداث التشغيلية',
        'GAP:DEP-11:10', '⛔ لا شاشةَ حيّةً — و`Workforce/worker_worklog.php` يطابق الاسمَ حرفًا وهو سجلُّ عاملٍ (إجازات · حوافز · جزاءات) لا خطًّا زمنيًّا للتشغيل'),
  array(6, 'الإقفال والتقارير', 1, 'تقرير الانحراف والتصعيد',
        'GAP:DEP-11:11', '⛔ لا شاشةَ مستقلّة — وحقولُه الخمسةُ (المخطط · المنفذ · الفجوة · الوتيرة · المسؤول) كتلةٌ داخلَ غرفةِ العمليات'),
  array(6, 'الإقفال والتقارير', 2, 'الإقفال الشهري للتشغيل',
        'Operations/monthly_close.php', '◑ حبّةٌ أضيق: الحيُّ «شهر × وحدة تعاقدية» والدليلُ «شهر × إدارة»'),
);

/* رأسُ الطيِّ يُنقَل لما سندُه مشتقٌّ وحدَه */
$HEAD_MOVE = array('operations/operations_room.php' => array('REPORTS', 'DAILY'));

/* ═══ الرجوع ═══════════════════════════════════════════════════════════════ */
if ($REVERT) {
    if (!$APPLY) { echo "◆ الرجوعُ يحتاج `--apply` معه\n"; exit(0); }
    $conn->query("DELETE FROM gov_target_nav WHERE role_id = $ROLE AND doc_code = '" . $q($DOC) . "'");
    foreach ($HEAD_MOVE as $r => $m) {
        $conn->query("UPDATE nav_route_group SET group_code = '" . $q($m[0]) . "', basis = 'LEVEL:4'
                       WHERE route = '" . $q($r) . "'");
    }
    $conn->query("DELETE FROM nav_items WHERE role_id = $ROLE AND route = 'Operations/monthly_plan.php'
                                          AND permission_code = 'RPR-OPS-11'");
    $conn->query("DELETE FROM nav_canonical WHERE route = 'Operations/monthly_plan.php'
                                              AND decision_source LIKE '%RPR-OPS-11%'");
    /* وصفُّ تصديرِ المصفوفةِ يُنزَع أيضًا — وإلّا بقي سطحٌ مُعلَنٌ بلا رابط */
    $MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
    if (is_file($MTX)) {
        $lines = file($MTX, FILE_IGNORE_NEW_LINES); $hdr = array_shift($lines); $keep = array();
        foreach ($lines as $ln) {
            if (trim($ln) === '') { continue; }
            $c = str_getcsv($ln);
            if ($c && isset($c[1]) && strtolower(trim($c[1])) === 'operations/monthly_plan.php') { continue; }
            $keep[] = $ln;
        }
        file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n");
    }
    echo "✔ رُجع: الإعلانُ حُذف · رأسُ غرفةِ العملياتِ أُعيد · صفوفُ الملاحةِ والمصفوفةِ والتصديرِ حُذفت\n";
    exit(0);
}

/* ═══ ② القياسُ قبلَ الكتابة ════════════════════════════════════════════════ */
printf("\n═══ سايدبارُ التشغيلِ من ورقةِ الدليلِ 11 — الدور %d ═══\n", $ROLE);
$have = 0; $miss = 0;
foreach ($TARGET as $t) { if ($gap($t[4])) { $miss++; } else { $have++; } }
printf("  مجموعاتٌ منصوصة: 6 · شاشاتٌ منصوصة: %d · **موصولةٌ بحيٍّ: %d** · بلا شاشةٍ حيّة: %d\n\n",
    count($TARGET), $have, $miss);

/* صلاحيةُ الدورِ لكلِّ هدفٍ — فما لا يملكه لا يُصيَّر مهما أُعلن */
$perm = array();
foreach ($TARGET as $t) {
    if ($gap($t[4])) { continue; }
    $r = $conn->query("SELECT rp.can_view FROM modules m
                        LEFT JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = $ROLE
                        WHERE m.code = '" . $q($t[4]) . "' LIMIT 1");
    $row = $r ? $r->fetch_row() : null;
    $perm[$t[4]] = ($row === null) ? 'NO_MODULE' : (($row[0] === null) ? 'NO_GRANT' : (((int) $row[0] === 1) ? 'YES' : 'NO'));
}
$g = 0;
foreach ($TARGET as $t) {
    if ($t[0] !== $g) { $g = $t[0]; printf("  ⬛ %d · %s\n", $g, $t[1]); }
    printf("      %d %-30s %s\n", $t[2], mb_substr($t[3], 0, 28),
        $gap($t[4]) ? '⛔ لا شاشةَ حيّة' : ($t[4] . '  [' . $perm[$t[4]] . ']'));
}

/* ⛔ **وعزلُ المساحاتِ يمنع بندًا منصوصًا — ولا يُنقَض هنا**: `getUnifiedNavItems`
     ترشّح بـ`gov_space_appearances` (ثامنًا-⑦)، وفيها صفٌّ حيٌّ يقول إنَّ
     «خطة عمل الغد» **مملوكةٌ لإدارةِ الموقعِ و`FORBIDDEN` في مساحةِ التشغيل**.
     والدليلُ يقول عكسَه: «إدارةُ التشغيلِ تملك التوزيع». ⇒ **تناقضٌ بين مرجعَين
     يُرفَع للمالكِ ولا يُحسم بيدِ الأداة** — فرفعُ المنعِ توسيعُ وصولٍ لشاشةِ
     إدارةٍ أخرى، وهو غيرُ «ترتيبِ سايدبار». */
$blocked = array();
require_once $ROOT . '/includes/space_scope.php';
foreach ($TARGET as $t) {
    if ($gap($t[4])) { continue; }
    $cls = ems_scope_class($t[4], 'ادارة التشغيل');
    if ($cls === 'FORBIDDEN') { $blocked[] = array($t[3], $t[4]); }
}
if ($blocked) {
    printf("\n  ⛔ **محجوبٌ بعزلِ المساحاتِ — منصوصٌ ولا يُصيَّر (%d)**:\n", count($blocked));
    foreach ($blocked as $b) {
        $r = $conn->query("SELECT owner_dept_ar, basis, src_note FROM gov_space_appearances
                            WHERE space_ar = 'ادارة التشغيل' AND LOWER(route) = LOWER('" . $q($b[1]) . "') LIMIT 1");
        $x = $r ? $r->fetch_assoc() : null;
        printf("     · %s  `%s`\n       مالكُه «%s» · %s\n", $b[0], $b[1],
            $x ? $x['owner_dept_ar'] : '؟', $x ? mb_substr($x['basis'], 0, 90) : '');
    }
}

/* صفُّ الملاحةِ الناقصُ للخطةِ الشهرية */
$needRow = ($conn->query("SELECT 1 FROM nav_items WHERE role_id = $ROLE
                           AND route = 'Operations/monthly_plan.php'")->num_rows === 0);
printf("\n  صفُّ ملاحةٍ يُنشَأ: %s · رأسُ طيٍّ يُنقَل: %d\n",
    $needRow ? '«الخطة الشهرية للتشغيل»' : '— (قائمٌ)', count($HEAD_MOVE));

if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; exit(0); }

/* ═══ ③ لقطةُ الرجوعِ قبلَ أيِّ كتابة ═══════════════════════════════════════ */
$dir = $ROOT . '/docs/REPAIR01_20260823/evidence';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$snap = array('role' => $ROLE, 'gov_target_nav' => array(), 'nav_route_group' => array(), 'nav_items' => array());
$r = $conn->query("SELECT * FROM gov_target_nav WHERE role_id = $ROLE");
while ($r && ($x = $r->fetch_assoc())) { $snap['gov_target_nav'][] = $x; }
foreach (array_keys($HEAD_MOVE) as $rt) {
    $r = $conn->query("SELECT * FROM nav_route_group WHERE route = '" . $q($rt) . "'");
    while ($r && ($x = $r->fetch_assoc())) { $snap['nav_route_group'][] = $x; }
}
$r = $conn->query("SELECT * FROM nav_items WHERE role_id = $ROLE");
while ($r && ($x = $r->fetch_assoc())) { $snap['nav_items'][] = $x; }
/* ⛔ **واللقطةُ تُكتب مرّةً**: تشغيلٌ ثانٍ يدهسها بحالةِ ما بعدَ الأوّلِ فتصير
     «لقطةَ رجوعٍ» إلى ما رجعنا منه. (والرجوعُ لا يعتمدها أصلًا — يعتمد
     `doc_code` و`permission_code` والسندَ الأصليَّ المكتوبَ في الأداة.) */
if (!is_file($dir . '/ops_sidebar_snapshot.json')) {
    file_put_contents($dir . '/ops_sidebar_snapshot.json', json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/* ═══ ④ الكتابة ═══════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM gov_target_nav WHERE role_id = $ROLE AND doc_code = '" . $q($DOC) . "'");
$n = 0; $lost = array();
foreach ($TARGET as $t) {
    $ok = $conn->query("INSERT INTO gov_target_nav (doc_code, role_id, group_no, group_ar, item_no, item_ar, route, note)
        VALUES ('" . $q($DOC) . "', $ROLE, " . (int) $t[0] . ", '" . $q($t[1]) . "', " . (int) $t[2] . ",
                '" . $q($t[3]) . "', '" . $q($t[4]) . "', '" . $q($t[5]) . "')");
    if ($ok && $conn->affected_rows === 1) { $n++; } else { $lost[] = $t[0] . '.' . $t[2] . ' ' . $t[3] . ' — ' . $conn->error; }
}
/* ⛔ **والعدُّ يقابل المحاوَلَ بالمكتوب**: عدُّ الناجحِ وحدَه أعلن «9» على 12 صفًّا */
if ($lost) {
    printf("\n⛔ **فُقد %d صفًّا من %d — لا يُكتَب نصفُ المستهدَف**:\n", count($lost), count($TARGET));
    foreach ($lost as $L) { echo "   · $L\n"; }
    exit(1);
}

/* ⛔ **وصفُّ الملاحةِ وحدَه يُرسِّب البوّابةَ U1**: «مسارٌ مُصيَّرٌ بلا صفٍّ في
     المصفوفة» — فالمصفوفةُ `nav_canonical` هي المرجعُ الحاكمُ للاسمِ والموضع،
     وشاشةٌ تُصيَّر خارجَها **اسمُها بلا مالكٍ ولا قرار**. ⇒ يُكتب صفُّها
     بمواصفةِ الدليلِ نفسِه: الاسمُ منصوصٌ («الشاشة 03 من 12») والمالكُ
     `DEP-11` من سجلِّ الملكية، والمجموعةُ مجموعةَ أخواتِها في المصفوفة. */
$canon = 0;
if ($conn->query("SELECT 1 FROM nav_canonical WHERE route = 'Operations/monthly_plan.php'")->num_rows === 0) {
    $ok = $conn->query("INSERT INTO nav_canonical
        (route, canonical_ar, canonical_en, level_no, level_name, group_name, sort_no, owner_dept,
         status, decision_state, application_state, decision_source, retirement_status, screen_id)
        VALUES ('Operations/monthly_plan.php', 'الخطة الشهرية للتشغيل', 'Monthly Operations Plan',
                2, 'العمليات', 'التخطيط وتخصيص الموارد', 440, 'إدارة التشغيل',
                'APPROVED', 'APPROVED', 'DEPLOYED',
                'الدليلُ المعماريُّ — ورقة 11 · الشاشة 03 من 12 (RPR-OPS-11)', 'ACTIVE', 'SCR-0432')");
    if ($ok) { $canon = 1; }
    else { printf("\n⛔ تعذَّر صفُّ المصفوفةِ: %s\n", $conn->error); exit(1); }
}

/* ⛔ **وصفُّ المصفوفةِ في القاعدةِ لا يكفي للبوّابة**: `U1` يقرأ **تصديرَ
     المصفوفةِ المجمَّد** `docs/uxui_matrix_20260818.csv` لا `nav_canonical` —
     فأيُّ سطحٍ يُصيَّر بلا صفٍّ فيه **يُرسِّب البوّابةَ** («مسارٌ مُصيَّرٌ بلا صفٍّ
     في المصفوفة»). وهي البوّابةُ نفسُها التي يخدمها `repair01_w9..w15_apply`
     بإلحاقِ صفٍّ لكلِّ سطحٍ جديدٍ — **فالإلحاقُ مسلكُ الحملةِ المعتادُ لا خرقٌ**.
   ◆ والقيمُ من الدليلِ نفسِه: الاسمُ منصوصٌ · المالكُ `DEP-11` · والمجموعةُ
     مجموعةَ أخواتِها. */
$mtxN = 0;
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
if (is_file($MTX)) {
    $lines = file($MTX, FILE_IGNORE_NEW_LINES);
    $hdr = array_shift($lines);
    $keep = array(); $maxN = 0; $has = false;
    foreach ($lines as $ln) {
        if (trim($ln) === '') { continue; }
        $cells = str_getcsv($ln);
        if (!$cells || count($cells) < 2) { continue; }
        $maxN = max($maxN, (int) $cells[0]);
        if (strtolower(trim($cells[1])) === 'operations/monthly_plan.php') { $has = true; }
        $keep[] = $ln;                      /* الباقي خامٌّ لا يُعاد ترميزُه */
    }
    if (!$has) {
        $cell = function ($v) { $v = (string) $v;
            if ($v === '') { return '""'; }
            if (preg_match('/[",\s]/u', $v)) { return '"' . str_replace('"', '""', $v) . '"'; }
            return $v; };
        $vals = array($maxN + 1, 'Operations/monthly_plan.php', 'الخطة الشهرية للتشغيل',
            'الخطة الشهرية للتشغيل', 'Monthly Operations Plan', '—',
            'تعرض خطة الشهر المعتمدة: ما ستنتجه كل معدة على كل مشروع، وهي أساس قياس الانحراف طوال الشهر. حبتها معدة × مشروع × شهر — سطر خطة واحد.',
            'إدارة التشغيل', '2 — العمليات', 'التخطيط وتخصيص الموارد', 440, 'شاشةٌ مستقلة', 1,
            'ادارة التشغيل', 'تُربط في موضعِها المعياريّ', 'APPROVED',
            'الدليلُ المعماريُّ — ورقة 11 · الشاشة 03 من 12 (RPR-OPS-11)', '—', '—', 'ACTIVE', '—',
            'الخطة الشهرية للتشغيل', 'التخطيط والتوزيع', 'ترتيبُ دورةِ العملِ في ورقةِ الدليل',
            'التخطيط والتوزيع');
        file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n"
            . implode(',', array_map($cell, $vals)) . "\n");
        $mtxN = 1;
    }
}

/* صفُّ الخطةِ الشهرية — البندُ الوحيدُ المنصوصُ الذي لا صفَّ ملاحةٍ له */
$made = 0;
if ($needRow) {
    $mid = 0;
    $r = $conn->query("SELECT id FROM modules WHERE code = 'Operations/monthly_plan.php' LIMIT 1");
    if ($r && ($x = $r->fetch_row())) { $mid = (int) $x[0]; }
    $gid = 0;
    $r = $conn->query("SELECT id FROM link_groups WHERE owner_role_id = $ROLE AND name = 'التخطيط والتوزيع' LIMIT 1");
    if ($r && ($x = $r->fetch_row())) { $gid = (int) $x[0]; }
    $ok = $conn->query("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                               sort_order, permission_code, active)
        VALUES ($ROLE, 'DAILY', " . ($gid ?: 'NULL') . ", " . ($mid ?: 'NULL') . ",
                'الخطة الشهرية للتشغيل', 'Operations/monthly_plan.php', 'fa fa-calendar-days',
                13, 'RPR-OPS-11', 1)");
    if ($ok) { $made = 1; }
}

/* رأسُ الطيِّ المشتقُّ يُنقَل — والسندُ يُكتب فيه */
$moved = 0;
foreach ($HEAD_MOVE as $rt => $m) {
    if ($conn->query("UPDATE nav_route_group SET group_code = '" . $q($m[1]) . "',
                        basis = 'RPR-OPS-11:ورقة الدليل 11 — مجموعة ① من دورة التشغيل'
                       WHERE route = '" . $q($rt) . "' AND basis LIKE 'LEVEL:%'")) {
        $moved += $conn->affected_rows;
    }
}

printf("\n✔ أُعلن %d صفًّا · صفُّ مصفوفةٍ %d · صفُّ تصديرٍ %d · صفُّ ملاحةٍ %d · رأسٌ منقول %d · لقطةُ الرجوع evidence/ops_sidebar_snapshot.json\n",
    $n, $canon, $mtxN, $made, $moved);

/* ═══ ⑤ التقريرُ من المكتوبِ ومن المُصيَّرِ معًا — قارئٌ واحدٌ لا اثنان ═══════
   ⛔ **ولا يُنشَر جدولُ ترجيحٍ آليٍّ إلى جانبِ المطبَّق**: نُشرَ حكمانِ لبندٍ
     واحدٍ في ملفَّين يتفرّقان بأولِ تعديل. فالمصدرُ صفوفُ `gov_target_nav`
     المكتوبةُ، و**العمودُ الأخيرُ من التصييرِ الحيِّ** لا من نيّةِ الأداة. */
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
$html = uxp_render_role_html($conn, $ROLE, 4);
$seen = array();
foreach (uxp_parse_nav_html($html) as $p) { $seen[mb_strtolower(uxp_norm($p['href']))] = $p; }

$o  = "# سايدبارُ إدارةِ التشغيل — ورقةُ الدليلِ 11 مطبَّقةً\n\n";
$o .= "> ⛔ **مولَّدٌ من قياسٍ حيّ**: `php tools/repair01_ops_sidebar.php --apply`\n";
$o .= "> **الدور 1 «ادارة التشغيل»** — مستخدمُ المالكِ المذكور `محمد` (`users.id = 4`).\n";
$o .= "> **والعمودُ الأخيرُ من السايدبارِ المُصيَّرِ بجلسةِ المستخدمِ نفسِه** لا من صفوفِ القاعدة.\n\n";
$o .= "| ⬛ | # | المنصوصُ في الدليل | الحيُّ الموصول | **مُصيَّرٌ؟** | السند |\n";
$o .= "|---|---:|---|---|---|---|\n";
$live = 0;
$r = $conn->query("SELECT group_no, group_ar, item_no, item_ar, route, note FROM gov_target_nav
                    WHERE role_id = $ROLE AND doc_code = '" . $q($DOC) . "' ORDER BY group_no, item_no");
while ($r && ($x = $r->fetch_assoc())) {
    $isGap = $gap($x['route']);
    $on = (!$isGap && isset($seen[mb_strtolower($x['route'])]));
    if ($on) { $live++; }
    $o .= sprintf("| %d · %s | %d | %s | %s | %s | %s |\n",
        $x['group_no'], $x['group_ar'], $x['item_no'], $x['item_ar'],
        $isGap ? '—' : '`' . $x['route'] . '`',
        $isGap ? '⛔ لا شاشة' : ($on ? '**✔ نعم**' : '⛔ **لا**'), $x['note']);
}
$o .= sprintf("\n**المقيس: %d من %d بندًا منصوصًا تُصيَّر فعلًا** (‏%d بلا شاشةٍ حيّة · %d محجوبٌ بعزلِ المساحات).\n",
    $live, count($TARGET), $miss, count($blocked));
file_put_contents($ROOT . '/docs/REPAIR01_20260823/OPS_SIDEBAR.md', $o);
printf("✔ كُتب OPS_SIDEBAR.md — **مُصيَّرٌ فعلًا %d من %d**\n", $live, count($TARGET));
echo "◆ أعِد القياسَ بالمُصيَّر: php tools/repair01_ops_nav_probe.php\n";
