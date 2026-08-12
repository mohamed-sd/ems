<?php
/**
 * 2027_02_13 — قرارا مالكٍ: الممنوحُ يُرى · ومديرُ حركةِ الموقعِ يُسجَّل
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **قرارُ المالك ①: «فعّلها كلَّها — الممنوحُ يُرى».**
 *   المقيس: 273 صفَّ تنقُّلٍ بـ`active = 0` **وموديولُه ممنوحٌ `can_view`** لدورِه
 *   — أي شاشةٌ مسموحةٌ بلا بابٍ في القائمة (الدور 1: 36 · 19: 24 · 5: 17 …).
 *   وقاعدةُ النظامِ المُعلَنة: «التبعيةُ تحدد القائمةَ **والصلاحيةُ ترشّح**» —
 *   فالمنحةُ هي المُرشِّح، و`active` مقبضٌ إداريٌّ لا حكمُ صلاحيةٍ ثانٍ يُخفي
 *   ما أذنت به المنحة.
 *   **وشرطُ المنحةِ محفوظٌ حرفيًّا**: لا يُفعَّل صفٌّ بلا `can_view` — فلا
 *   يُعرض بابٌ لمن لا يملكه. و**الصفوفُ بلا موديولٍ لا تُمسّ** (ثوابتُ القائمة:
 *   لا منحةَ تُرشِّحها فلا يُقاس عليها هذا الحكم).
 *
 * ◆ **قرارُ المالك ②: `site_movement_mgr` موقعيٌّ بخطٍّ وظيفيٍّ إلزامي.**
 *   `org_assignment_types` فيه 13 نوعًا، و«الحركة» ليست في أيٍّ من المستويين
 *   — فترفضها `AssignmentService` بحقٍّ: «نوعُ تكليفٍ غيرُ معرَّف — يُضاف صفًّا
 *   في `org_assignment_types` لا كودًا». وثلاثةُ فواحصَ تطلبها
 *   (`permit_gate_test` · `uat_break_test` · `uat_load_decision_test`).
 *   **والشكلُ منقولٌ من نظائرِه الموقعيةِ السبعِ حرفيًّا**: `level='site'` ·
 *   `requires_functional_line=1` · `is_unit_head=0` · `active=1` — كما
 *   `site_maintenance_officer` و`site_transport_coordinator` و`site_warehouse_keeper`.
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ كلُّ قرارٍ بعده.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ══ ① الممنوحُ يُرى ═══════════════════════════════════════════════════════ */
echo "══ ① تفعيلُ كلِّ صفٍّ مطفأٍ وموديولُه ممنوح ══\n";
$before = (int) $db->query("SELECT COUNT(*) FROM nav_items n
                             WHERE n.active = 0 AND COALESCE(n.module_id,0) > 0
                               AND EXISTS(SELECT 1 FROM role_permissions p
                                           WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                             AND COALESCE(p.can_view,0) = 1)")->fetch_row()[0];
echo "  مطفأٌ وممنوح: {$before}\n";

if ($before > 0) {
    if ($db->query("UPDATE nav_items n SET n.active = 1
                     WHERE n.active = 0 AND COALESCE(n.module_id,0) > 0
                       AND EXISTS(SELECT 1 FROM role_permissions p
                                   WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                     AND COALESCE(p.can_view,0) = 1)") === false) {
        fwrite(STDERR, '✘ ' . $db->error . "\n"); exit(1);
    }
    echo '  ✔ فُعِّل: ' . $db->affected_rows . "\n";
} else { echo "  ○ لا صفَّ يحتاج تفعيلًا\n"; }

$leftUngranted = (int) $db->query("SELECT COUNT(*) FROM nav_items n
                                    WHERE n.active = 0 AND COALESCE(n.module_id,0) > 0")->fetch_row()[0];
$leftNoModule = (int) $db->query("SELECT COUNT(*) FROM nav_items
                                   WHERE active = 0 AND COALESCE(module_id,0) = 0")->fetch_row()[0];
echo "  ○ باقٍ مطفأً **بلا منحة** (صوابٌ — لا يُعرض بابٌ لمن لا يملكه): {$leftUngranted}\n";
echo "  ○ وباقٍ مطفأً **بلا موديول** (ثوابتُ قائمةٍ لا تُرشَّح بمنحة): {$leftNoModule}\n";

/* بابُ الرئيسيةِ يبقى صفًّا واحدًا — التفعيلُ الجماعيُّ قد يُحيي ثانيًا */
$homeBad = (int) $db->query("SELECT COUNT(*) FROM roles r
                             WHERE (r.status='1' OR r.status=1) AND r.id <> -1
                               AND (SELECT COUNT(*) FROM nav_items n
                                     WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) <> 1")
                    ->fetch_row()[0];
if ($homeBad > 0) {
    echo "  ⚠ التفعيلُ أحيا رئيسيةً ثانيةً في {$homeBad} دورًا — يُعاد العقدُ «واحدٌ»\n";
    $r = $db->query("SELECT role_id FROM roles r WHERE (r.status='1' OR r.status=1) AND r.id <> -1
                       AND (SELECT COUNT(*) FROM nav_items n
                             WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) > 1");
    /* الأدوارُ ذاتُ أكثرَ من صفٍّ: يُبقى الأولُ ترتيبًا ويُطفأ الباقي */
    $ids = array();
    $q = $db->query("SELECT r.id FROM roles r WHERE (r.status='1' OR r.status=1) AND r.id <> -1
                       AND (SELECT COUNT(*) FROM nav_items n
                             WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) > 1");
    while ($q && ($x = $q->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $rid) {
        $keep = $db->query("SELECT id FROM nav_items WHERE role_id = {$rid} AND door='HOME' AND active=1
                             ORDER BY sort_order, id LIMIT 1")->fetch_row();
        if (!$keep) { continue; }
        $db->query("UPDATE nav_items SET active = 0 WHERE role_id = {$rid} AND door='HOME'
                     AND active = 1 AND id <> " . (int) $keep[0]);
        $db->query("UPDATE nav_items SET label_ar = 'الرئيسية' WHERE id = " . (int) $keep[0]);
    }
    $homeBad = (int) $db->query("SELECT COUNT(*) FROM roles r
                                 WHERE (r.status='1' OR r.status=1) AND r.id <> -1
                                   AND (SELECT COUNT(*) FROM nav_items n
                                         WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) <> 1")
                        ->fetch_row()[0];
    echo '  ' . ($homeBad === 0 ? '✔ أُعيد العقد' : '✘ بقي ' . $homeBad) . "\n";
} else { echo "  ✔ وبابُ الرئيسيةِ ما زال صفًّا واحدًا لكلِّ دور\n"; }

/* والأسماءُ تبقى موحَّدةً في النشطِ من HOME */
$db->query("UPDATE nav_items SET label_ar = 'الرئيسية'
             WHERE door='HOME' AND active=1 AND label_ar <> 'الرئيسية'");

/* ══ ② مديرُ حركةِ الموقع ═════════════════════════════════════════════════ */
echo "\n══ ② تسجيلُ `site_movement_mgr` ══\n";
$has = (int) $db->query("SELECT COUNT(*) FROM org_assignment_types
                          WHERE type_code = 'site_movement_mgr'")->fetch_row()[0];
if ($has) {
    echo "  ○ مسجَّلٌ سلفًا\n";
} else {
    /* الشكلُ من نظيرٍ موقعيٍّ قائمٍ — نسخٌ لا اختيار */
    $tpl = $db->query("SELECT `level`, requires_functional_line, is_unit_head, active,
                              default_capabilities_json
                         FROM org_assignment_types
                        WHERE type_code = 'site_maintenance_officer' LIMIT 1")->fetch_assoc();
    $lvl  = $tpl ? (string) $tpl['level'] : 'site';
    $fn   = $tpl ? (int) $tpl['requires_functional_line'] : 1;
    $head = $tpl ? (int) $tpl['is_unit_head'] : 0;
    $act  = $tpl ? (int) $tpl['active'] : 1;
    $st = $db->prepare("INSERT INTO org_assignment_types
              (type_code, name_ar, `level`, requires_functional_line, is_unit_head, active)
              VALUES ('site_movement_mgr', 'مدير حركة الموقع', ?, ?, ?, ?)");
    $st->bind_param('siii', $lvl, $fn, $head, $act);
    if (!$st->execute()) { fwrite(STDERR, '✘ ' . $st->error . "\n"); exit(1); }
    $st->close();
    echo "  ✔ سُجِّل: مستوًى «{$lvl}» · خطٌّ وظيفيٌّ إلزاميّ={$fn} · رأسُ وحدة={$head}\n";
}

echo "\n── أنواعُ التكليفِ الموقعيةُ بعده\n";
$r = $db->query("SELECT type_code, name_ar, requires_functional_line, is_unit_head
                  FROM org_assignment_types WHERE `level` = 'site' ORDER BY type_code");
while ($x = $r->fetch_assoc()) {
    echo '  ' . str_pad($x['type_code'], 30) . str_pad(mb_substr($x['name_ar'], 0, 24), 26)
       . 'وظيفيّ=' . $x['requires_functional_line'] . ' رأس=' . $x['is_unit_head'] . "\n";
}

$ok = ($homeBad === 0);
echo "\n" . ($ok ? "✅ الممنوحُ يُرى · والحركةُ مسجَّلةٌ نوعًا لا كودًا.\n" : "⚠ راجِع أعلاه\n");
exit($ok ? 0 : 1);
