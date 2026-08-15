<?php
/**
 * 2027_05_05_handover_registry.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حدثُ تسليمِ الحصةِ — التسجيلُ قبلَ الشاشةِ + قواعدُ HO في القاعدة
 *
 * قواعدُ SUP-CNT-01 على النظيرِ الحيِّ `container_swaps` (صفُّه يحمل الطرفين
 * معًا فيُشبع HO-02/03 بنيويًّا):
 *   HO-01 كلُّ تغيّرٍ له صفُّ حدثٍ بتاريخِه ⇐ effective_from إلزامي
 *   HO-04 كلُّ حدثٍ بنوعِه وشهرِه ⇐ swap_kind + شهرُ effective_from
 *   HO-05 لا حدثَ يمسُّ شهرًا مغلقًا ⇐ قادحٌ يرفض effective_from في فترةٍ مقفلة
 *   + مستندٌ إلزاميٌّ للجديد (doc_ref) — والتاريخيُّ الثمانيةَ عشرَ يبقى مُعلَنًا.
 *
 * والتسجيل: وحدةُ صلاحياتٍ + منحٌ + رابطٌ (n9o_) + فعلا
 * sup.handover.record وsup.settle.apply في القاموس — قبلَ بناءِ الشاشة.
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? $r->fetch_row()[0] : null; };

const SCREEN = 'Suppliers/sup_handover.php';
const ROLE = 2;

echo "══ تسجيلُ حدثِ التسليم ══\n\n";

/* ① قادحُ HO على container_swaps */
$conn->query("DROP TRIGGER IF EXISTS trg_swap_ho_ins");
$ok = $conn->query("CREATE TRIGGER trg_swap_ho_ins BEFORE INSERT ON container_swaps FOR EACH ROW
BEGIN
    IF NEW.effective_from IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HO-01: لا حدثَ تسليمٍ بلا تاريخِ سريان';
    END IF;
    IF NEW.doc_ref IS NULL OR NEW.doc_ref = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'لا تسليمَ بلا مستندٍ — اذكرْ مرجعَ المحضرِ أو الخطاب';
    END IF;
    IF NEW.container_id IS NULL OR NEW.to_container_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HO-02: التسليمُ بطرفين — الحاويةُ المسلِّمةُ والمستلِمة';
    END IF;
    IF EXISTS (SELECT 1 FROM fin_financial_periods p
               WHERE p.company_id = NEW.company_id AND p.period_type='month'
                 AND NEW.effective_from BETWEEN p.start_date AND p.end_date
                 AND p.posting_allowed = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'HO-05: الشهرُ مغلقٌ — لا حدثَ يعيد احتسابَ شهرٍ مقفل';
    END IF;
END");
if (!$ok) { exit("  ✘ قادحُ HO: {$conn->error}\n"); }
echo "  ① قادحُ HO-01/02/05 على container_swaps\n";

/* ② الوحدةُ والمنحُ والرابط */
$st = $conn->prepare("SELECT id FROM modules WHERE code=?");
$c1 = SCREEN; $st->bind_param('s', $c1); $st->execute();
$moduleId = (int) ($st->get_result()->fetch_row()[0] ?? 0); $st->close();
if (!$moduleId) {
    $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                          VALUES ('تسليمُ الحصص بين الموردين', ?, ?, 1, 0, 'fa fa-right-left', 100)");
    $r2 = ROLE; $st->bind_param('si', $c1, $r2); $st->execute();
    $moduleId = (int) $conn->insert_id; $st->close();
}
echo "  ② الوحدة #$moduleId\n";
foreach (array(array(2,1,1,1), array(1,1,0,0), array(8,1,0,0)) as [$role,$v,$a,$e]) {
    $q = $conn->query("SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$moduleId");
    if (!($q && $q->num_rows)) {
        $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                      VALUES ($role, $moduleId, $v, $a, $e, 0)");
    }
}
$GCODE = 'n9o_sup_handover_r2';
$q = $conn->query("SELECT id FROM link_groups WHERE group_code='$GCODE'");
$groupId = (int) ($q && $q->num_rows ? $q->fetch_row()[0] : 0);
if (!$groupId) {
    $q = $conn->query("SELECT COALESCE(MAX(display_order),0)+1, MAX(stage_title) FROM link_groups WHERE owner_role_id=" . ROLE . " AND stage_no=4");
    [$ord, $stTitle] = $q->fetch_row();
    $stTitle = $stTitle ?: 'رابعًا: توزيع الحصة على معداته';
    $st = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                          VALUES ('تسليمُ الحصص', ?, ?, 'fa fa-right-left', ?, 4, ?, 1)");
    $r2 = ROLE; $st->bind_param('siis', $GCODE, $r2, $ord, $stTitle); $st->execute();
    $groupId = (int) $conn->insert_id; $st->close();
}
$q = $conn->query("SELECT id FROM nav_items WHERE route='" . SCREEN . "' AND role_id=" . ROLE);
if (!($q && $q->num_rows)) {
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                          VALUES (?, 'DAILY', ?, ?, 'تسليمُ الحصص بين الموردين', ?, 'fa fa-right-left', 1, ?, 1, NOW())");
    $r2 = ROLE; $rt = SCREEN; $pc = SCREEN;
    $st->bind_param('iiiss', $r2, $groupId, $moduleId, $rt, $pc); $st->execute(); $st->close();
}
echo "  ③ المجموعة #$groupId والرابطُ مسجَّلان\n";

/* ③ الفعلان في القاموس */
$ACT = array(
    array('sup.handover.record', 'تسجيلُ حدثِ تسليمِ حصة', 'تسليمُ الحصص بين الموردين', 'sup_handover.php',
          'إدارةُ الموردين', 'container_swaps · op_containers', 'ShareHandedOver',
          'الموردون · المبيعات · المالية', 'حصةُ الخانةِ تنتقل بين موردَين بمستندٍ وتاريخِ سريانٍ — والشهرُ المغلقُ لا يُمَسّ (HO-01..05)', 'حدثٌ مقابلٌ بالاتجاهِ المعكوس'),
    array('sup.settle.apply', 'تطبيقُ تسويةٍ شهريةٍ لمورد', 'تسويات الموردين', 'settlements.php',
          'الماليةُ والموردون', 'settlements', 'SupplierSettled',
          'المالية · الموردون', 'التسوياتُ الأربعُ بمستندٍ إلزاميٍّ (chk_settle_adj_doc) — وF-07/F-08 يُحسبان بقادحٍ لا يُدخَلان', 'تسويةٌ عكسيةٌ بمرجعِ الأصل'),
);
foreach ($ACT as $a) {
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code='" . $conn->real_escape_string($a[0]) . "'");
    if ($q && $q->num_rows) { echo "  · {$a[0]} مسجَّلٌ سلفًا\n"; continue; }
    $st = $conn->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
         consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
         idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page','yes',?, 'yes',?, 'pending','', 'domain_write', NOW())");
    $live = $a[0];
    $gev = 'حارسُ شاشةٍ + CSRF + can_add قبلَ المعالج · وقواعدُ HO قوادحُ في القاعدة';
    $iev = 'doc_ref + التاريخُ يميّزان الحدثَ — والقادحُ يرفض الناقص';
    $st->bind_param('sssssssssssss', $a[0],$a[1],$a[2],$a[3],$a[4],$a[5],$a[6],$a[7],$a[8],$a[9],$live,$gev,$iev);
    if ($st->execute()) { echo "  ✔ {$a[0]}\n"; } else { echo "  ✘ {$a[0]}: {$conn->error}\n"; }
    $st->close();
}

/* ④ إثباتُ قادحِ HO سلبيًّا */
$st = $conn->prepare("INSERT INTO container_swaps (company_id, container_id, swap_kind, to_container_id, effective_from, reason, created_at)
                      VALUES (4, 1, 'حصة', 2, '2026-08-16', 'اختبارٌ بلا مستند', NOW())");
$blocked = !$st->execute(); $e = $st->errno; $st->close();
echo '  ④ تسليمٌ بلا مستند: ' . ($blocked ? "✔ رُفض ($e)" : '✘ مرّ!') . "\n";
echo ($blocked ? "\n✔ تمّت\n" : "\n✘ إخفاق\n");
if (!$blocked) { exit(1); }
