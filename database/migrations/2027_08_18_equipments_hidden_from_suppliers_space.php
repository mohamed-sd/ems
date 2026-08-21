<?php
/**
 * 2027_08_18_equipments_hidden_from_suppliers_space.php
 * «سجلُّ المعدات» يُخفى من سايدبارِ إدارةِ الموردين — والقراءةُ تبقى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الطلب** (المالك · 2026-08-21) — «اخفِ رابطَ سجل المعدات من إدارةِ
 *   الموردين». وهو عكسُ ما فتحته `2027_08_13_equipments_for_suppliers_mgr.php`
 *   قبلَ يومٍ واحد — **في الظهورِ وحدَه لا في الصلاحية**.
 *
 * ◆ **والإخفاءُ غيرُ السحب** — ولذلك يُقفل قفلان ويُتركُ قفلان صراحةً:
 *   ③ `nav_items.active = 0` — الصفُّ الذي أنشأته هجرةُ المنحِ ليُظهر الرابطَ
 *      يُطفأ: «بلا صفٍّ نشِطٍ **لا رابطَ** ولو صحّت الصلاحيةُ كلُّها».
 *   ④ `gov_space_appearances.cls = 'FORBIDDEN'` — قرارُ عزلِ الإداراتِ يُكتب
 *      صريحًا، فيُرشَّح المسارُ من `getUnifiedNavItems()` ومن `dynamic_nav`
 *      ومن البحثِ العامِّ ومن `excel.php` ومن `action_guard` **في هذه المساحةِ
 *      وحدَها**. وهذا هو حرفًا ما عليه الإداراتُ السبعُ الأخرى غيرُ المالكة.
 *   ① `gov_profile_items` (SUP-G7 · allow=1) و② `role_permissions.can_view=1`
 *      **تُتركان كما هما** — وهو الوضعُ نفسُه لاثنَي عشرَ دورًا يرون الشاشةَ
 *      بصلاحيةٍ ومساحتُهم `FORBIDDEN`: الرابطُ مخفيٌّ والمقصدُ باقٍ، فرابطٌ من
 *      شاشةٍ أخرى أو مهمةٌ أو اعتمادٌ يبلغ الشاشةَ ولا يُردُّ ٤٠٣.
 *
 * ◆ **ولو أُريد سحبُ الصلاحيةِ كلِّها** فذلك أمرٌ واحدٌ لا هذه الهجرة:
 *     php database/migrations/2027_08_13_equipments_for_suppliers_mgr.php --revert
 *   (يحذف الأقفالَ الأربعةَ ويُعيد الحالَ إلى ما قبلَ 2026-08-20 — ويترك
 *    المساحةَ **بلا صفٍّ مُصنَّفٍ**، وهو الفراغُ الذي تتجنّبه هذه الهجرةُ عمدًا.)
 *
 * ◆ **ولا يُحذف صفُّ اللقطةِ ولا صفُّ التبعية** — يُقلَبان ويُطفآن. فالحذفُ يُفقد
 *   القرارَ ويُعيد المساحةَ «مجهولًا صامتًا» في لقطةٍ تُقرأ حكمًا؛ والقلبُ يُبقي
 *   الأثرَ ويجعل العكسَ سطرًا واحدًا.
 *
 * ◆ **وما قِيس قبلَ الكتابة**: لا صفَّ لهذا المسارِ في `nav_canonical_current`
 *   للدور 2 — فلا يُصطنع رابطُه من السجلِّ في الفرعِ ②-ب من `printEmsTenGroupNav`
 *   بعدَ إطفاءِ صفِّ التبعية. (ولو كان له صفٌّ هناك لعاد الرابطُ من بابٍ ثانٍ
 *   والإخفاءُ مُعلَنٌ كاذب.)
 *
 * التشغيل:  php database/migrations/2027_08_18_equipments_hidden_from_suppliers_space.php [--revert]
 * الإثبات:  php tests/equipments_suppliers_mgr_http_proof.php
 *           php tests/space_isolation_negative_test.php "ادارة الموردين"
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
$REVERT = in_array('--revert', $argv, true);

/* ── الثوابتُ الحاكمة ─────────────────────────────────────────────────── */
$ROUTE = 'Equipments/equipments.php';
$ROLE  = 2;                    /* مدير الموردين — EMS_ROLE_SUPPLIERS_MGR */
$SPACE = 'ادارة الموردين';     /* gov_space_roles.space_ar للدور 2 */
$LABEL = 'سجل المعدات';

/* حالتا الظهورِ — الأمامُ إخفاءٌ والخلفُ ما كتبته هجرةُ المنحِ حرفًا */
$CLS_HIDE  = 'FORBIDDEN';
$STEP_HIDE = 6;
$BASIS_HIDE = 'قرارُ المالك 2026-08-21: يُخفى رابطُ سجلِّ المعدات من سايدبارِ إدارةِ الموردين — والقراءةُ تبقى مقصدًا لا بابًا';
$CLS_BACK  = 'CONTEXTUAL_READ_ONLY';
$STEP_BACK = 0;
$BASIS_BACK = 'قرارُ المالك 2026-08-20: منظرٌ مرجعيٌّ للقراءةِ يحتاجه مديرُ الموردين — الملكيةُ تبقى لإدارةِ الأسطول';

/* ── الموديلُ يُحلُّ من السجلِّ لا يُخمَّن ─────────────────────────────── */
$st = $conn->prepare("SELECT id, name FROM modules WHERE code = ? LIMIT 1");
$st->bind_param('s', $ROUTE); $st->execute();
$mod = $st->get_result()->fetch_assoc(); $st->close();
if (!$mod) { exit("✗ لا صفَّ في `modules` للمسار {$ROUTE} — أُوقفت الهجرة\n"); }
$MODID = (int) $mod['id'];

echo ($REVERT ? "◆ عكسُ الإخفاء: إعادةُ «{$LABEL}» إلى سايدبارِ «{$SPACE}»\n"
              : "◆ إخفاءُ «{$LABEL}» من سايدبارِ «{$SPACE}»\n");
echo "  الموديل: #{$MODID} «{$mod['name']}» · المسار: {$ROUTE} · الدور: {$ROLE}\n\n";

$did = array(); $skip = array();

/* ══ ③ صفُّ التبعيةِ — مصدرُ الرابطِ نفسُه ═══════════════════════════════ */
$ex = $conn->prepare("SELECT id, active FROM nav_items WHERE role_id = ? AND route = ? LIMIT 1");
$ex->bind_param('is', $ROLE, $ROUTE); $ex->execute();
$nav = $ex->get_result()->fetch_assoc(); $ex->close();
$want = $REVERT ? 1 : 0;
if (!$nav) {
    $skip[] = '③ nav_items: لا صفَّ للدور ' . $ROLE . ' على هذا المسار — لا شيءَ يُقلب';
} elseif ((int) $nav['active'] === $want) {
    $skip[] = '③ nav_items: الصفُّ على المطلوبِ سلفًا (active=' . $want . ')';
} else {
    $up = $conn->prepare("UPDATE nav_items SET active = ? WHERE id = ?");
    $up->bind_param('ii', $want, $nav['id']);
    if (!$up->execute()) { echo "  ✗ ③ nav_items: {$up->error}\n"; }
    else { $did[] = '③ nav_items #' . $nav['id'] . ": active " . (int) $nav['active'] . " ⇐ {$want}"; }
    $up->close();
}

/* ══ ④ لقطةُ عزلِ الإدارات — القرارُ يُكتب صريحًا لا يُترك فراغًا ═══════ */
$ex = $conn->prepare("SELECT id, cls, rule_step FROM gov_space_appearances
                       WHERE space_ar = ? AND LOWER(route) = LOWER(?) LIMIT 1");
$ex->bind_param('ss', $SPACE, $ROUTE); $ex->execute();
$app = $ex->get_result()->fetch_assoc(); $ex->close();
$clsWant  = $REVERT ? $CLS_BACK : $CLS_HIDE;
$stepWant = $REVERT ? $STEP_BACK : $STEP_HIDE;
$basisWant = $REVERT ? $BASIS_BACK : $BASIS_HIDE;
if (!$app) {
    $skip[] = "④ gov_space_appearances: لا صفَّ للمسارِ في «{$SPACE}» — لا شيءَ يُقلب";
} elseif ($app['cls'] === $clsWant) {
    $skip[] = "④ gov_space_appearances: الصنفُ {$clsWant} سلفًا";
} else {
    /* `src_class` لقطةُ المصدرِ فلا تُمَسّ — والقرارُ يعيش في `cls`/`rule_step`/`basis`
       (وهو حرفًا شكلُ صفوفِ الإداراتِ السبعِ الأخرى: src=CONTEXTUAL_READ_ONLY · cls=FORBIDDEN) */
    $up = $conn->prepare("UPDATE gov_space_appearances
                             SET cls = ?, rule_step = ?, basis = ?, updated_at = NOW()
                           WHERE id = ?");
    $up->bind_param('sisi', $clsWant, $stepWant, $basisWant, $app['id']);
    if (!$up->execute()) { echo "  ✗ ④ gov_space_appearances: {$up->error}\n"; }
    else { $did[] = "④ gov_space_appearances #{$app['id']}: cls «{$app['cls']}» ⇐ «{$clsWant}» · rule_step={$stepWant}"; }
    $up->close();
}

/* ══ ①② الصلاحيةُ لا تُمَسُّ — ويُعلَن ذلك عددًا لا وعدًا ═══════════════ */
$q = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? (int) $x[0] : 0; };
$canView = $q("SELECT COALESCE(can_view,0) FROM role_permissions WHERE role_id={$ROLE} AND module_id={$MODID}");
$profHit = $q("SELECT COUNT(*) FROM gov_profile_items gpi
                 JOIN gov_role_profiles p ON p.profile_id = gpi.profile_id AND p.state='active'
                 JOIN gov_authority_grants g ON g.profile_id = p.profile_id AND g.revoked_at IS NULL
                 JOIN users u ON u.id = g.user_id AND u.role = {$ROLE}
                WHERE gpi.item_kind='screen' AND gpi.item_ref='{$ROUTE}' AND gpi.allow=1");
$skip[] = "① gov_profile_items: {$profHit} بندًا نافذًا — لم يُمَسّ (الإخفاءُ ليس سحبًا)";
$skip[] = "② role_permissions: can_view={$canView} — لم تُمَسّ (الإخفاءُ ليس سحبًا)";

/* ══ الإثباتُ بعدَ الكتابة ═══════════════════════════════════════════════ */
echo "  ▸ ما وقع:\n";
foreach ($did as $d) { echo "     ✔ {$d}\n"; }
if (empty($did)) { echo "     — لا شيء\n"; }
echo "  ▸ ما تُرك:\n";
foreach ($skip as $s) { echo "     · {$s}\n"; }

echo "\n  ▸ الحالةُ الآن (قياسٌ لا ادّعاء):\n";
echo "     ③ nav_items نشِط  : " . $q("SELECT COUNT(*) FROM nav_items WHERE role_id={$ROLE} AND route='{$ROUTE}' AND active=1")
   . ' (المطلوب ' . ($REVERT ? 1 : 0) . ")\n";
echo "     ④ FORBIDDEN هنا   : " . $q("SELECT COUNT(*) FROM gov_space_appearances
                                        WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}') AND cls='FORBIDDEN'")
   . ' (المطلوب ' . ($REVERT ? 0 : 1) . ")\n";
echo "     ④' صفُّ المساحة   : " . $q("SELECT COUNT(*) FROM gov_space_appearances
                                        WHERE space_ar='{$SPACE}' AND LOWER(route)=LOWER('{$ROUTE}')")
   . " (المطلوب 1 — لا فراغَ صامت)\n";
echo "     ② can_view        : {$canView} (لم تُمَسّ)\n";
$sisters = $q("SELECT COUNT(*) FROM gov_space_appearances WHERE LOWER(route)=LOWER('{$ROUTE}') AND cls='FORBIDDEN'");
$total   = $q("SELECT COUNT(*) FROM gov_space_appearances WHERE LOWER(route)=LOWER('{$ROUTE}')");
echo "     ⑤ مساحاتُ المسار  : {$total} · الممنوعُ فيها {$sisters} · المالكةُ «إدارة الأسطول»\n";

echo "\n  ▸ الإثباتُ المُلزِم:\n";
echo "     php tests/equipments_suppliers_mgr_http_proof.php\n";
echo "     php tests/space_isolation_negative_test.php \"{$SPACE}\"\n";
