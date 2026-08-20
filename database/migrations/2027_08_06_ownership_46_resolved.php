<?php
/**
 * 2027_08_06_ownership_46_resolved.php — حسمُ الستَّةِ والأربعين (ثامنًا-٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب: «لا تُزال ولا تُنقل ولا تُمنع شاشةٌ قبلَ حسمِ المِلكية — فالمِلكيةُ
 *   تحدّد ما هو ممنوعٌ أصلًا… **واحسمْها أنت بأقوى الشواهدِ ولا ترفعها لي**».
 *
 * ◆ **والشاهدُ الآليُّ قِيس أولًا** (`tools/uxui_ownership_evidence.php`) بأربعِ
 *   طبقاتِ استبعادٍ **كلُّها مقيسةٌ لا مؤلَّفةٌ بيدي**:
 *   ① طبقةُ المنصةِ (13 جدولًا يكتبها ≥34٪ من الشاشات) — **جدولٌ يكتبه الجميعُ
 *      لا يُملِّك أحدًا**.
 *   ② مصارفُ الأثرِ (6 جداولَ من الناشرِ والمروحة) — وهي قاعدةُ المالكِ نفسُها:
 *      **من يُنتج الواقعةَ يملك شاشتَها ومن يستهلك أثرَها يملك مستندَه هو**،
 *      فشاشةٌ يهبط أثرُها في دفترِ الماليةِ **مُنتِجةٌ لا مملوكةٌ لها**.
 *   ③ سجلاتُ الضبطِ في شاهدِ القراءة (25 جدولًا يقرؤه ≥34٪).
 *   ④ والقراءةُ تُوسَم `BY_READS` صراحةً ولا تُخلط بالكتابة — **شاهدٌ أضعفُ،
 *      وإخفاءُ ضعفِه تحتَ اسمٍ واحدٍ تزويرُ درجةِ ثقة**.
 *
 * ◆ **وحيث بلغَ الشاهدُ الآليُّ حدَّه حُسم بالدورةِ المستندية** — بتفويضِك:
 *   «متى ترددتَ فاختر ما توجبه دورةُ العملِ والدورةُ المستنديةُ لتلك الإدارة».
 *   وأربعَ عشرةَ شاشةَ ضبطٍ ماليٍّ (`acc_*`/`ctrl_*`/`tre_*`) رفعها الشاهدُ إلى
 *   «الحوكمة» لأنها تقرأ `gov_*` — **وذلك مخزنُ ضبطِها لا مالكُها**:
 *   `tre_sod_matrix` فصلُ مهامِّ الخزينةِ و`ctrl_authority_limits` سقفُ
 *   الاعتمادِ المالي — **دورتُهما ماليةٌ قطعًا**. فبقيَ المالكُ ماليةً.
 *
 * ◆ **وأكثرُ الستَّةِ والأربعين ليست خطأَ مالكٍ بل نقصَ ظهور** — وهو ما حذّرت
 *   منه المواصفةُ حرفًا: «قد يكون المالكُ صحيحًا وظهورُها ناقصًا». فشاشةٌ
 *   ماليةٌ تظهر عند المراجعِ الداخليِّ وحدَه **مِلكيتُها صحيحةٌ وغيابُها عن
 *   ماليتِها هو العيب** — ويُسجَّل `APPEARANCE_MISSING` فجوةً في مساحةِ المالك،
 *   **ولا يُزال شيءٌ**: الإزالةُ هنا تحذف آخرَ مدخلٍ للشاشة.
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$conn->query("CREATE TABLE IF NOT EXISTS `gov_ownership_rulings` (
    `route`        VARCHAR(190) NOT NULL,
    `owner_before` VARCHAR(120) NOT NULL,
    `owner_after`  VARCHAR(120) NOT NULL,
    `witness`      VARCHAR(255) NOT NULL COMMENT 'الشاهدُ ومصدرُه',
    `witness_kind` ENUM('DOMAIN_WRITE','DATA_READ','DOC_CYCLE','NONE') NOT NULL,
    `ruling`       ENUM('OWNER_CONFIRMED','OWNER_CHANGED','APPEARANCE_MISSING') NOT NULL,
    `reason`       VARCHAR(400) NOT NULL DEFAULT '',
    `decided_at`   DATETIME NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ثامنًا-٣ حسمُ المِلكيةِ المشكوكة — بالشاهدِ أو بالدورةِ المستندية'");

/* ══ نقضُ الشاهدِ الآليِّ بالدورةِ المستندية — مُعلَنٌ بسببِه لا صامتًا ══════ */
$DOC_CYCLE_OVERRIDE = array(
    /* ضبطُ الماليةِ والخزينة: مخزنُ ضبطِها `gov_*` ومالكُها ماليةٌ */
    'Finance/acc_approval_chain.php'    => array('المالية والخزينة', 'سلسلةُ اعتمادٍ ماليةٌ — دورتُها ماليةٌ و`gov_*` مخزنُ ضبطِها لا مالكُها'),
    'Finance/acc_my_day.php'            => array('المالية والخزينة', 'يومُ المحاسبِ — دورةُ عملٍ ماليةٌ بحتة'),
    'Finance/acc_routing_matrix.php'    => array('المالية والخزينة', 'توجيهُ المستندِ المالي — دورةٌ مستنديةٌ ماليةٌ'),
    'Finance/acc_specializations.php'   => array('المالية والخزينة', 'تخصّصاتُ المحاسبين — بنيةُ فريقِ المالية'),
    'Finance/ctrl_authority_limits.php' => array('المالية والخزينة', 'سقفُ الاعتمادِ المالي — قيدٌ على الدورةِ الماليةِ نفسِها'),
    'Finance/ctrl_dept_propagation.php' => array('المالية والخزينة', 'سريانُ الضبطِ الماليِّ على الإدارات'),
    'Finance/ctrl_doc_registry.php'     => array('المالية والخزينة', 'سجلُّ المستندِ المالي — الدورةُ المستنديةُ للمالية'),
    'Finance/ctrl_doc_variance.php'     => array('المالية والخزينة', 'انحرافُ المستندِ المالي'),
    'Finance/ctrl_quality_kpis.php'     => array('المالية والخزينة', 'جودةُ الأداءِ المحاسبي'),
    'Finance/ctrl_role_migration.php'   => array('المالية والخزينة', 'ترحيلُ أدوارِ المالية'),
    'Finance/ctrl_supervision.php'      => array('المالية والخزينة', 'إشرافُ المالية على فريقِها'),
    'Finance/tre_cycle_stages.php'      => array('المالية والخزينة', 'مراحلُ دورةِ الخزينة — والخزينةُ ماليةٌ'),
    'Finance/tre_sod_matrix.php'        => array('المالية والخزينة', 'فصلُ مهامِّ الخزينة — قيدٌ داخليٌّ ماليّ'),
    'Finance/tre_unit_roles.php'        => array('المالية والخزينة', 'أدوارُ وحدةِ الخزينة'),
    /* الحركةُ يُنتجها التشغيلُ — والأسطولُ يملك الأصلَ لا حركتَه */
    'movement/map_page.php'             => array('إدارة التشغيل', 'خريطةُ الحركةِ — **التشغيلُ يُنتج الحركةَ والأسطولُ يملك الأصلَ لا حركتَه**'),
    'movement/movement_operations.php'  => array('إدارة التشغيل', 'عملياتُ الحركة — واقعتُها من التشغيل'),
    'admin/ops_manager_board.php'       => array('إدارة التشغيل', 'لوحةُ مديرِ التشغيل — دورةُ عملِ التشغيلِ نفسِها'),
    'Oprators/select_project.php'       => array('القوى التشغيلية', 'اختيارُ مشروعِ المشغِّل — خطوةٌ في دورةِ القوى التشغيلية'),
    'Governance/gov_reports.php'        => array('الحوكمة والالتزام', 'تقاريرُ الحوكمة — وقراءتُها لجداولِ الموارد البشريةِ استهلاكٌ لا مِلكية'),
    'Tickets/my_tickets.php'            => array('مركز البلاغات', 'بلاغاتي — بندٌ شخصيٌّ يملكه مركزُ البلاغات'),
);

$tsv = $ROOT . '/docs/uxui/ownership_evidence_46.tsv';
if (!is_file($tsv)) { exit("✘ شغّلْ أولًا: php tools/uxui_ownership_evidence.php\n"); }

$ins = $conn->prepare(
    "INSERT INTO gov_ownership_rulings (route, owner_before, owner_after, witness, witness_kind, ruling, reason)
     VALUES (?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE owner_after=VALUES(owner_after), witness=VALUES(witness),
        witness_kind=VALUES(witness_kind), ruling=VALUES(ruling), reason=VALUES(reason)"
);
if (!$ins) { exit("تعذّر التحضير: {$conn->error}\n"); }

$upd = $conn->prepare("UPDATE gov_space_appearances
                          SET ownership = ?, owner_dept_ar = ?, basis = ?
                        WHERE route = ? AND src_ownership = 'ERROR_SUSPECTED'");

$confirmed = 0; $changed = 0; $missing = 0; $overrode = 0;
$fh = fopen($tsv, 'r');
while (($line = fgets($fh)) !== false) {
    $c = explode("\t", rtrim($line, "\r\n"));
    if (count($c) < 7) { continue; }
    list($id, $route, $space, $ownerBefore, $proposed, $verdict, $ev) = $c;

    $kind = (strpos($verdict, 'BY_READS') !== false) ? 'DATA_READ'
          : (($verdict === 'NO_WRITE') ? 'NONE' : 'DOMAIN_WRITE');

    if (isset($DOC_CYCLE_OVERRIDE[$route])) {
        $after  = $DOC_CYCLE_OVERRIDE[$route][0];
        $reason = 'نقضُ الشاهدِ الآليِّ بالدورةِ المستندية: ' . $DOC_CYCLE_OVERRIDE[$route][1];
        $kind   = 'DOC_CYCLE';
        $overrode++;
    } else {
        $after  = (strpos($verdict, 'CONFIRMED') === 0 || $verdict === 'NO_WRITE') ? $ownerBefore : $proposed;
        $reason = (strpos($verdict, 'CONFIRMED') === 0)
            ? 'الشاهدُ يوافق المالكَ المسجَّل'
            : ($verdict === 'NO_WRITE' ? 'لا شاهدَ آليّ — يبقى المالكُ المسجَّلُ ولا يُبدَّل بلا دليل'
                                        : 'الشاهدُ يخالف — والمالكُ يتبع الشاهد');
    }

    /* ◆ المالكُ صحيحٌ والظهورُ في مساحةٍ واحدةٍ ليست مساحتَه ⇒ **نقصُ ظهورٍ لا خطأُ مالك** */
    $sameSpace = (mb_strpos($space, mb_substr($after, 0, 8)) !== false)
              || (mb_strpos($after, mb_substr($space, 0, 8)) !== false);
    if ($after === $ownerBefore && !$sameSpace) {
        $ruling = 'APPEARANCE_MISSING'; $missing++;
        $reason .= ' · **والعيبُ نقصُ ظهورٍ في مساحةِ المالكِ لا خطأُ نسبة — ولا تُزال، فإزالتُها تحذف آخرَ مدخل**';
    } elseif ($after === $ownerBefore) {
        $ruling = 'OWNER_CONFIRMED'; $confirmed++;
    } else {
        $ruling = 'OWNER_CHANGED'; $changed++;
    }

    $ins->bind_param('sssssss', $route, $ownerBefore, $after, $ev, $kind, $ruling, $reason);
    $ins->execute();

    $newState = ($ruling === 'OWNER_CHANGED') ? 'CORRECTED' : 'CONFIRMED_BY_EVIDENCE';
    $basis = mb_substr($ruling . ' — ' . $reason, 0, 255);
    $upd->bind_param('ssss', $newState, $after, $basis, $route);
    $upd->execute();
}
fclose($fh);
$ins->close(); $upd->close();

echo "══ حسمُ المِلكيةِ المشكوكة — الخطوةُ الثالثة ══\n";
echo "  الحصيلة: مالكٌ مؤكَّد={$confirmed} · مالكٌ صُحِّح={$changed} · **نقصُ ظهورٍ لا خطأُ مالك={$missing}**\n";
echo "  ◆ نُقض الشاهدُ الآليُّ بالدورةِ المستنديةِ في {$overrode} — وكلٌّ بسببِه المكتوبِ في السجل.\n";

$q = $conn->query("SELECT ruling, COUNT(*) n FROM gov_ownership_rulings GROUP BY ruling ORDER BY n DESC");
echo "\n  ┌ الحكم\n";
while ($q && ($x = $q->fetch_assoc())) { printf("  │ %-20s %3d\n", $x['ruling'], $x['n']); }
echo "  ├ الشاهد\n";
$q = $conn->query("SELECT witness_kind, COUNT(*) n FROM gov_ownership_rulings GROUP BY witness_kind ORDER BY n DESC");
while ($q && ($x = $q->fetch_assoc())) { printf("  │ %-20s %3d\n", $x['witness_kind'], $x['n']); }
echo "  └──────────────────────────\n";

$q = $conn->query("SELECT COUNT(*) FROM gov_space_appearances WHERE ownership = 'ERROR_SUSPECTED'");
$left = $q ? (int) $q->fetch_row()[0] : -1;
echo "\n  **مِلكيةٌ مشكوكةٌ متبقّية: {$left}**"
   . ($left === 0 ? " — والشرطُ الأولُ للإغلاقِ مُستوفًى\n" : " — لم يكتمل\n");
exit($left === 0 ? 0 : 1);
