<?php
/**
 * 2027_03_18 — تسجيلُ شاشةِ التسعيرِ اليوميِّ ومنحُها للإدارةِ المالية
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-12**: مصدرُ تعديلِ الأسعارِ هو «التحديثُ الوقتيُّ
 * للأسعارِ **من الإدارةِ المالية** … مع إمكانيةِ تحديدِ السعرِ لليومِ بشكلٍ يوميّ».
 *
 * ── ولماذا شاشةٌ جديدةٌ بدلَ منحِ الماليةِ شاشةَ العقودِ القائمة ─────────────
 * `Contracts/price_terms.php` فيه أربعةُ أفعالٍ وبتَّان فقط:
 *   · `add_reading`   ⇒ `can_add`   — تسجيلُ سعرِ اليوم (**عملُ المالية** بالقرار)
 *   · `save_term`     ⇒ `can_add`/`can_edit` — كتابةُ **بندِ العقد** (الدور 12)
 *   · `run_review`    ⇒ `can_edit`
 *   · `approve_revision` ⇒ `can_edit`
 * فمنحُ الماليةِ `can_add` هناك **يمنحُها كتابةَ بنودِ العقودِ** — منحٌ زائدٌ
 * لا يطلبه القرارُ. فالفصلُ بشاشةٍ بموديولِها: التسعيرُ للمالية، والبندُ للعقود.
 *
 * ── الحارسُ الذي يُغلَق هنا ─────────────────────────────────────────────────
 * **شاشةٌ غيرُ مسجَّلةٍ في `modules` تصير مفتوحةً للجميع** (جذرُ INJ-0008 الذي
 * أُغلق مركزيًّا) — فالتسجيلُ **شرطُ أمنٍ** لا ترتيبَ قوائم. ولا يُنشر ملفُّ
 * شاشةٍ بلا صفِّ موديولٍ وصفوفِ صلاحياتٍ صريحة.
 *
 * ◆ والمنحُ **صريحٌ ومحدود**: الدورُ 17 (إدارةُ المالية) و19 (مديرُ إدارةِ
 *   المالية) عرضًا وإضافةً وتعديلًا — ولا حذفَ لأنَّ السعرَ المسجَّلَ واقعةٌ.
 *   والدورُ 12 (العقود) **عرضًا فقط** ليرى ما سُعِّر على عقودِه ولا يُسعّر.
 * ◆ ولا يُمَسُّ صفٌّ قائمٌ لشاشةٍ أخرى، والهجرةُ عاطلةٌ تُعاد بلا أثر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$CODE = 'Finance/daily_pricing_fin.php';
$NAME = 'التسعير اليومي';
$OWNER = 17;                       // إدارةُ المالية — مالكُ الشاشةِ بالقرار

/* ── ٠ الملفُّ موجودٌ فعلًا؟ لا يُسجَّل موديولٌ لملفٍّ غيرِ قائم ───────────────── */
$file = dirname(__DIR__, 2) . '/' . $CODE;
if (!is_file($file)) {
    fwrite(STDERR, "الملفُّ غيرُ موجود: {$CODE} — لا يُسجَّل موديولٌ لعدم\n");
    exit(1);
}
echo "── ٠ الملفُّ قائمٌ (" . number_format(filesize($file)) . " بايتًا)\n";

/* ── ① صفُّ الموديول ───────────────────────────────────────────────────────── */
$esc = $db->real_escape_string($CODE);
$q = $db->query("SELECT id FROM modules WHERE code = '{$esc}'");
$mid = ($q && $q->num_rows) ? (int) $q->fetch_assoc()['id'] : 0;
if ($mid > 0) {
    echo "── ① الموديولُ مسجَّلٌ سلفًا (#{$mid})\n";
} else {
    $ok = $db->query("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                      VALUES ('" . $db->real_escape_string($NAME) . "', '{$esc}', {$OWNER}, 0, 0,
                              'fa fa-calendar-day', 0)");
    if ($ok === false) { fwrite(STDERR, '① فشل: ' . $db->error . "\n"); exit(1); }
    $mid = (int) $db->insert_id;
    if ($mid <= 0) { fwrite(STDERR, "① لم يُرجَع معرِّفٌ للموديول\n"); exit(1); }
    echo "── ① سُجّل الموديولُ #{$mid} لمالكٍ = الدور {$OWNER}\n";
}

/* ── ② المنحُ الصريحُ المحدود ─────────────────────────────────────────────── */
$grants = array(
    17 => array(1, 1, 1, 0),   // إدارةُ المالية — تُسعّر
    19 => array(1, 1, 1, 0),   // مديرُ إدارةِ المالية — يُسعّر ويعتمد
    12 => array(1, 0, 0, 0),   // العقود — يرى ما سُعِّر ولا يُسعّر
);
$pcols = array();
$r = $db->query('SHOW COLUMNS FROM role_permissions');
while ($r && ($x = $r->fetch_assoc())) { $pcols[$x['Field']] = true; }
$hasCo = isset($pcols['company_id']);
foreach ($grants as $role => $bits) {
    list($v, $a, $e, $d) = $bits;
    $w = "role_id = {$role} AND module_id = {$mid}";
    $q = $db->query("SELECT id FROM role_permissions WHERE {$w}");
    if ($q && $q->num_rows) {
        echo "── ② منحُ الدور {$role} موجودٌ سلفًا\n";
        continue;
    }
    $cols = 'role_id, module_id, can_view, can_add, can_edit, can_delete';
    $vals = "{$role}, {$mid}, {$v}, {$a}, {$e}, {$d}";
    if ($hasCo) { $cols .= ', company_id'; $vals .= ', 4'; }
    $ok = $db->query("INSERT INTO role_permissions ({$cols}) VALUES ({$vals})");
    if ($ok === false) { fwrite(STDERR, "② فشل منحُ الدور {$role}: " . $db->error . "\n"); exit(1); }
    echo "── ② مُنح الدور {$role}: عرضٌ {$v} · إضافةٌ {$a} · تعديلٌ {$e} · حذفٌ {$d}\n";
}

/* ── ③ جسٌّ: لا دورَ غيرَ الثلاثةِ يملك عرضًا (فالمنحُ محدودٌ لا مفتوح) ──────── */
$others = array();
$r = $db->query("SELECT role_id FROM role_permissions
                  WHERE module_id = {$mid} AND can_view = 1
                    AND role_id NOT IN (17, 19, 12)");
while ($r && ($x = $r->fetch_assoc())) { $others[] = (string) $x['role_id']; }
echo '── ③ أدوارٌ أخرى بعرضٍ: ' . (count($others) ? implode(' · ', $others) . ' ✘' : 'لا شيء ✔') . "\n";
if ($others) { fwrite(STDERR, "منحٌ غيرُ مقصودٍ — راجِع قبل الالتزام\n"); exit(1); }

/* ── ④ جسٌّ: مَن يُسعّر لا يكتب بنودَ العقودِ (لا منحَ زائدًا) ───────────────── */
$termMod = $db->query("SELECT id FROM modules WHERE code = 'Contracts/price_terms.php'");
$termMod = ($termMod && $termMod->num_rows) ? (int) $termMod->fetch_assoc()['id'] : 0;
if ($termMod > 0) {
    $bad = $db->query("SELECT role_id FROM role_permissions
                        WHERE module_id = {$termMod} AND role_id IN (17, 19)
                          AND (can_add = 1 OR can_edit = 1)");
    $names = array();
    while ($bad && ($x = $bad->fetch_assoc())) { $names[] = (string) $x['role_id']; }
    echo '── ④ أدوارُ الماليةِ بكتابةٍ على بندِ العقد (#' . $termMod . '): '
       . (count($names) ? implode(' · ', $names) . ' ⚠ منحٌ زائد' : 'لا شيء ✔ — الفصلُ قائم') . "\n";
} else {
    echo "── ④ شاشةُ بنودِ العقدِ غيرُ مسجَّلةٍ — الجسُّ لا يُشغَّل ويُعلَن\n";
}

echo "\n✅ التسعيرُ للمالية بشاشتِها وموديولِها، وبندُ العقدِ يبقى للعقود — بلا منحٍ زائد.\n";
exit(0);
