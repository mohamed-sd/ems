<?php
/**
 * 2027_08_17_nav_timesheet_link_to_view.php — رابطُ التايم شيت في السايدبار
 *                                             يقود إلى **سجلِّ** الوحدات لا إلى مُنتقي النوع
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك (2026-08-21)**: «رابطُ التايم شيت في سايدبار مدير التشغيل يجب
 *   أن يؤدِّيَ إلى `Timesheet/view_timesheet.php` بدل `Timesheet/timesheet_type.php`»
 *   ثم: «**أيضًا في سايدبار مدير الموردين**».
 *   ⇐ فالهجرةُ تُدار **بقائمةِ أدوارٍ** (`ROLES`) لا بدورٍ مكتوبٍ في متنِها،
 *     ويُضاف الدورُ التالي بسطرٍ واحدٍ حين يُطلب.
 *
 * ◆ **التشخيص — الرابطُ لا يذكر الوجهةَ المشكوَّ منها أصلًا**:
 *   صفُّ التنقُّلِ يخزِّن `Timesheet/timesheet.php` **بلا `?type=`**،
 *   و`timesheet.php` سطرَ 717-720 يشترط `type ∈ {1,2,3}` وإلا
 *   **`header("Location: timesheet_type.php")`**. فالمُصيَّرُ سليمٌ والمقصدُ
 *   منحرفٌ بإعادةِ توجيهٍ لا يراها فاحصُ الروابطِ المكسورة.
 *   ⇐ لذلك يُصحَّح **الصفُّ** لا الصفحة: الوجهةُ تُكتب صريحةً.
 *
 * ◆ **ومصدرانِ لا مصدرٌ واحد — وإلا عاد الرابطُ توأمًا**: بعدَ تحويلِ
 *   `nav_items` وحدَه ظهر رابطانِ لا رابط. فطبقةُ «صفرِ الفقد» في
 *   `printEmsTenGroupNav` §② تصطنع رابطًا لكلِّ مسارٍ يحمله **السجلُّ**
 *   (`nav_canonical_current` لهذا الدور) ولا صفَّ تبعيةٍ له — فبقاءُ صفِّ الدورِ
 *   على `timesheet/timesheet.php` في السجلِّ أعادَ الوجهةَ القديمةَ اصطناعًا
 *   بأيقونةِ `fa fa-link`. ⇐ يُحوَّل **موضعُ الدورِ في السجلِّ** أيضًا، فيحتفظ
 *   الرابطُ باسمِه (`cur_label`) وقسمِه (`cur_group`) وترتيبِه (`cur_order`)
 *   ولا يُصطنع توأمُه.
 *
 * ◆ **ولا يُفتح قفلٌ جديد**: كلُّ دورٍ في القائمةِ يملك `can_view=1` على الوحدةِ
 *   المقصودةِ في `role_permissions` سلفًا — والهجرةُ **تتحقَّق من ذلك وتتخطّى
 *   الدورَ برمزِ خروجٍ غيرِ صفريٍّ** إن لم يكن، فلا يُصنع رابطٌ يقود إلى 403.
 *   والمسارُ الجديدُ مُصنَّفٌ في `nav_route_group` تحت `DAILY` — بابُ الصفِّ نفسِه.
 *
 * ◆ **وما لم يتغيَّر**: الدخولُ إلى الإدخالِ باقٍ — `view_timesheet.php` يحمل
 *   زرَّ «رجوع» إلى `timesheet_type.php`. ولا يُمسُّ دورٌ خارجَ القائمةِ ولا صفٌّ
 *   آخرُ ولا صلاحيةٌ ولا اسمُ شاشة، ولا يُمسُّ صفُّ `nav_canonical` المعياريُّ
 *   (المصفوفةُ v3 ملكُ جولةِ الحوكمةِ لا هذه).
 *
 * التشغيل:  php database/migrations/2027_08_17_nav_timesheet_link_to_view.php
 * الرجوع :  php database/migrations/2027_08_17_nav_timesheet_link_to_view.php --revert
 * الشاهد :  php tests/nav_timesheet_link_to_view_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/** الأدوارُ المطلوبةُ بنصِّ المالك — أضِف رقمًا هنا لا في المتن. */
$ROLES      = array(1, 2);                          // 1 ادارة التشغيل · 2 ادارة الموردين
$ROUTE_ENTRY = 'Timesheet/timesheet.php';           // الوجهةُ القديمة (تُعيد التوجيه)
$ROUTE_VIEW  = 'Timesheet/view_timesheet.php';      // الوجهةُ المطلوبة

$revert    = in_array('--revert', $argv, true);
$fromRoute = $revert ? $ROUTE_VIEW  : $ROUTE_ENTRY;
$toRoute   = $revert ? $ROUTE_ENTRY : $ROUTE_VIEW;
$fromKey   = mb_strtolower($fromRoute);
$toKey     = mb_strtolower($toRoute);

/* ── ① الوحدةُ الهدف — بالرمزِ لا برقمٍ محفوظ (الأرقامُ تختلف بين النسخ) ─── */
$modId = function (mysqli $c, $code) {
    // أدنى id عند التكرار — سلوكُ حارسِ الصلاحياتِ نفسِه، فلا يفترق الفحصُ عن الحكم.
    $st = $c->prepare("SELECT MIN(id) FROM modules WHERE code = ?");
    $st->bind_param('s', $code); $st->execute();
    $st->bind_result($id); $st->fetch(); $st->close();
    return $id === null ? 0 : (int) $id;
};
$targetMod = $modId($conn, $toRoute);
if ($targetMod === 0) { exit("✘ لا وحدةَ مسجَّلةٌ للمسار «{$toRoute}» في `modules` — أُوقفت الهجرة\n"); }

/* المجموعة: المسارُ الجديدُ يجب أن يقع في بابِ صفوفِه نفسِه */
$grp     = $conn->query("SELECT group_code FROM nav_route_group WHERE route = '" . $conn->real_escape_string($toKey) . "'");
$grpCode = ($grp && ($g = $grp->fetch_row())) ? $g[0] : null;

echo "الوجهة: «{$fromRoute}» ⇦ «{$toRoute}» (module_id={$targetMod} · تصنيف=" . var_export($grpCode, true) . ")\n";
echo "───────────────────────────────────────────────────────────────\n";

$problems = 0;
foreach ($ROLES as $role) {
    $r = (int) $role;

    /* ── ② الصلاحيةُ شرطُ الرابط: لا يُصنع رابطٌ يقود إلى 403 ─────────────── */
    $st = $conn->prepare("SELECT can_view FROM role_permissions WHERE role_id = ? AND module_id = ?");
    $st->bind_param('ii', $r, $targetMod); $st->execute();
    $st->bind_result($canView); $has = $st->fetch(); $st->close();
    if (!$has || (int) $canView !== 1) {
        echo "✘ الدور {$r}: بلا can_view على الوحدة {$targetMod} — تُخطّي (امنحِ العرضَ ثم أعِد التشغيل)\n";
        $problems++;
        continue;
    }

    /* ── ③ صفُّ التبعية ──────────────────────────────────────────────────── */
    $st = $conn->prepare("SELECT id, door, module_id, route, permission_code, label_ar
                            FROM nav_items WHERE role_id = ? AND route = ? AND active = 1");
    $st->bind_param('is', $r, $fromRoute); $st->execute();
    $res = $st->get_result(); $navRow = $res ? $res->fetch_assoc() : null; $st->close();

    if ($navRow === null) {
        /* عطالة: إن كان الصفُّ يحمل الوجهةَ المطلوبةَ سلفًا فالتبعيةُ مُطبَّقة —
           لكنَّ **نصفَ تطبيقٍ ليس تطبيقًا**: يُستكمل تحويلُ السجلِّ هنا وإلا بقي
           التوأمُ المصطنَعُ وأُعلن اكتمالٌ كاذب. */
        $st = $conn->prepare("SELECT id FROM nav_items WHERE role_id = ? AND route = ? AND active = 1");
        $st->bind_param('is', $r, $toRoute); $st->execute();
        $st->bind_result($existing); $done = $st->fetch(); $st->close();
        if ($done) {
            $st = $conn->prepare("UPDATE nav_canonical_current SET route = ? WHERE role_id = ? AND route = ?");
            $st->bind_param('sis', $toKey, $r, $fromKey); $st->execute();
            $fixed = $st->affected_rows; $st->close();
            echo "↺ الدور {$r}: مُطبَّقٌ سلفًا (nav_items#{$existing})"
               . ($fixed > 0 ? " — واستُكمل تحويلُ موضعِ السجلِّ ({$fixed})" : "") . "\n";
        } else {
            echo "✘ الدور {$r}: لا صفَّ تنقُّلٍ نشطًا بالمسار «{$fromRoute}» — تُخطّي\n";
            $problems++;
        }
        continue;
    }

    if ($grpCode === null || $grpCode !== $navRow['door']) {
        echo "⚠ الدور {$r}: تصنيفُ الوجهةِ = " . var_export($grpCode, true)
           . " وبابُ الصفِّ = {$navRow['door']} — الرابطُ قد يقع خارجَ مجموعتِه\n";
    }

    /* ── ④ التحويل — والمُرجَعُ يُقاس لا يُفترض (config يضبط mysqli على عدم الرمي) ── */
    $navId = (int) $navRow['id'];
    $st = $conn->prepare("UPDATE nav_items
                             SET route = ?, module_id = ?, permission_code = ?, updated_at = NOW()
                           WHERE id = ?");
    $st->bind_param('sisi', $toRoute, $targetMod, $toRoute, $navId);
    if (!$st->execute()) { exit("✘ الدور {$r}: التحويلُ فشل: {$st->error}\n"); }
    $st->close();

    /* ── ④-ب السجلُّ يتبع التبعية: موضعُ الدورِ ينتقل مع الرابطِ ───────────
       المفتاحُ (route, role_id) ومساراتُه بحروفٍ صغيرة كما يطبِّع uxuiNavBaseRoute. */
    $st = $conn->prepare("SELECT cur_label, cur_group, cur_order FROM nav_canonical_current WHERE role_id = ? AND route = ?");
    $st->bind_param('is', $r, $fromKey); $st->execute();
    $res = $st->get_result(); $curRow = $res ? $res->fetch_assoc() : null; $st->close();
    $curNote = ' · لا صفَّ له في السجل';
    if ($curRow !== null) {
        $st = $conn->prepare("UPDATE nav_canonical_current SET route = ? WHERE role_id = ? AND route = ?");
        $st->bind_param('sis', $toKey, $r, $fromKey);
        if (!$st->execute()) { exit("✘ الدور {$r}: تحويلُ موضعِ السجلِّ فشل: {$st->error}\n"); }
        $st->close();
        $curNote = " · السجل: «{$curRow['cur_group']}» بترتيب {$curRow['cur_order']} كما هما";
    }

    /* ── ⑤ الشاهد: يُعادُ القراءةُ من القاعدةِ لا يُصدَّق مُرجَعُ التنفيذِ وحدَه ── */
    $res   = $conn->query("SELECT route, module_id, permission_code, label_ar FROM nav_items WHERE id = {$navId}");
    $after = $res ? $res->fetch_assoc() : null;
    if (!$after || $after['route'] !== $toRoute) { exit("✘ الدور {$r}: القراءةُ بعدَ الكتابةِ لا تُطابق المطلوب\n"); }

    echo "✔ الدور {$r}: nav_items#{$navId} «{$after['label_ar']}» ⇦ {$after['route']}"
       . " (module_id={$after['module_id']}){$curNote}\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo ($problems === 0 ? "اكتمل" : "اكتمل بتخطّي {$problems} دورًا")
   . " · ثم: php database/migrate.php dump-schema\n";
exit($problems === 0 ? 0 : 1);
