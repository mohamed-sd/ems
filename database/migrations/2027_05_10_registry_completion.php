<?php
/**
 * 2027_05_10_registry_completion.php
 * ═══════════════════════════════════════════════════════════════════════════
 * إكمالُ السجلاتِ — خمسةُ أبوابٍ في هجرةٍ واحدةٍ (كلُّها تسجيلٌ لا شاشات):
 *
 * ① AC-GOV-02 «كتابةٌ بلا باب»: أغلفةُ حوكمةِ الإداراتِ ملكُ إدارتِها
 *   (sit⇐6 · ops⇐1 · flt⇐3 · mnt⇐13)، والمشرفون (7·10·11·14) قراءةٌ بلا
 *   كتابة — «من أنشأ لا يعتمد» بابُه، لا مشرفَ يوقّع حوكمةَ إدارته. ولمن
 *   كتابتُه مشروعةٌ (المبيعاتُ في اعتمادِ الساعات st.06 · مديرُ الماليةِ في
 *   التسعيرِ اليومي) يُفتح بابُه رابطًا.
 * ② NM-04: مجموعةُ دورِ 19 «السجلات والملفات» بخمسةَ عشرَ عنصرًا تُشطر —
 *   «خمسٌ إلى سبعٍ وما زاد يُدمج أو يُشطر».
 * ③ ترتيبُ السايدبارِ بالدورةِ المستندية (TSP-0156..0171): أسماءُ المراحلِ
 *   أفعالًا لدورَي المبيعاتِ (12) والموردين (2) — تُحدَّث stage_title وحدَها
 *   (nav09_verify يقارن اسمَ المجموعةِ لا عنوانَ المرحلة).
 * ④ أفعالُ سلسلةِ الوحداتِ العشرة (st.03/04/05/08/09 + كشفا العميلِ والمورد
 *   + ap.shares.allocate + ap.oblig.generate) في القاموسِ مربوطةً بشاشاتِها
 *   الحيّةِ أو الجديدة.
 * ⑤ قاموسُ المبتدئ شاشةً مسجَّلةً (main/glossary.php) للأدوارِ 12·2·6.
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
$navAdd = function (int $role, string $route, string $label, int $groupId, int $moduleId) use ($conn) {
    $q = $conn->query("SELECT 1 FROM nav_items WHERE role_id=$role AND route='" . $conn->real_escape_string($route) . "'");
    if ($q && $q->num_rows) { return; }
    $st = $conn->prepare("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                          VALUES (?, 'DAILY', ?, ?, ?, ?, 'fa fa-circle-dot', 50, ?, 1, NOW())");
    $st->bind_param('iiisss', $role, $groupId, $moduleId, $label, $route, $route);
    $st->execute(); $st->close();
};

echo "══ إكمالُ السجلات ══\n\n";

/* ── ① الحوكمة: ملكيةٌ ومنحٌ وأبواب ── */
foreach (array('Operations/gov_dept_sit.php' => 6, 'Operations/gov_dept_ops.php' => 1,
               'Equipments/gov_dept_flt.php' => 3, 'Maintenance/gov_dept_mnt.php' => 13) as $code => $owner) {
    $conn->query("UPDATE modules SET owner_role_id=$owner WHERE code='" . $conn->real_escape_string($code) . "'");
}
echo "  ①-أ ملكيةُ أغلفةِ الحوكمةِ لإدارتِها\n";
$dropped = 0;
foreach (array(array(7, 'Operations/gov_dept_ops.php'), array(10, 'Equipments/gov_dept_flt.php'),
               array(11, 'Equipments/gov_dept_flt.php'), array(14, 'Maintenance/gov_dept_mnt.php')) as [$role, $code]) {
    $conn->query("UPDATE role_permissions rp JOIN modules m ON m.id=rp.module_id
                  SET rp.can_add=0, rp.can_edit=0, rp.can_delete=0
                  WHERE rp.role_id=$role AND m.code='" . $conn->real_escape_string($code) . "'");
    $dropped += $conn->affected_rows;
}
echo "  ①-ب مشرفون صاروا قراءةً: $dropped منحة\n";
/* بابانِ لكتابةٍ مشروعة */
$mApr = (int) $one("SELECT id FROM modules WHERE code='Approvals/hours_approval.php'");
$g12 = (int) $one("SELECT id FROM link_groups WHERE owner_role_id=12 AND stage_no=5 AND group_code LIKE 'n9s%' ORDER BY display_order LIMIT 1");
if ($mApr && $g12) { $navAdd(12, 'Approvals/hours_approval.php', 'اعتمادُ ساعاتِ الوحدات (بوابةُ المبيعات)', $g12, $mApr); }
$mPrc = (int) $one("SELECT id FROM modules WHERE code='Finance/daily_pricing_fin.php'");
$g19 = (int) $one("SELECT id FROM link_groups WHERE owner_role_id=19 AND group_code LIKE 'n9s%' ORDER BY stage_no, display_order LIMIT 1");
if ($mPrc && $g19) { $navAdd(19, 'Finance/daily_pricing_fin.php', 'التسعيرُ اليومي', $g19, $mPrc); }
echo "  ①-ج بابا المبيعاتِ والتسعير فُتحا\n";

/* ── ② شطرُ مجموعةِ دورِ 19 ── */
$gBig = (int) $one("SELECT g.id FROM link_groups g JOIN nav_items n ON n.group_id=g.id AND n.active=1
                    WHERE g.owner_role_id=19 AND g.name='السجلات والملفات'
                    GROUP BY g.id HAVING COUNT(*) > 7 LIMIT 1");
if ($gBig) {
    $row = $conn->query("SELECT group_code, stage_no, stage_title, display_order FROM link_groups WHERE id=$gBig")->fetch_assoc();
    $g2 = (int) $one("SELECT id FROM link_groups WHERE group_code='n9o_records2_r19'");
    if (!$g2) {
        $st = $conn->prepare("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                              VALUES ('الملفاتُ والكشوف', 'n9o_records2_r19', 19, 'fa fa-folder-open', ?, ?, ?, 1)");
        $ord = (int) $row['display_order'] + 1; $sn = (int) $row['stage_no']; $stt = (string) $row['stage_title'];
        $st->bind_param('iis', $ord, $sn, $stt); $st->execute();
        $g2 = (int) $conn->insert_id; $st->close();
    }
    $conn->query("UPDATE nav_items n JOIN (
                     SELECT id FROM nav_items WHERE group_id=$gBig AND active=1
                     ORDER BY sort_order DESC, id DESC LIMIT 8) x ON x.id=n.id
                  SET n.group_id=$g2");
    echo '  ② شُطرت مجموعةُ دورِ 19: نُقل ' . $conn->affected_rows . " عنصرًا إلى «الملفاتُ والكشوف»\n";
} else { echo "  ② لا مجموعةَ فوقَ السبعةِ لدور 19\n"; }

/* ── ③ أسماءُ المراحلِ أفعالًا (TSP-0156..0171) ── */
$SAL = array(1 => 'أولًا: نبدأ من العميل', 2 => 'ثانيًا: نتفاوض ونسعّر', 3 => 'ثالثًا: نوقّع العقد',
             4 => 'رابعًا: نوزّع الحاويات', 5 => 'خامسًا: نتابع التنفيذ', 6 => 'سادسًا: نفوتر ونحصّل',
             7 => 'متابعةُ إدارتي', 8 => 'تقاريرُ المبيعات');
$SUP = array(1 => 'أولًا: نبدأ من المورد', 2 => 'ثانيًا: نوقّع عقدَه', 3 => 'ثالثًا: نتحقق من قدرته',
             4 => 'رابعًا: نعطيه حصتَه', 5 => 'خامسًا: نتابع تنفيذَه', 6 => 'سادسًا: نحاسبه ونصرف',
             7 => 'متابعةُ إدارتي', 8 => 'سابعًا: نقيّمه أو نُنهي عقدَه');
$n3 = 0;
foreach (array(12 => $SAL, 2 => $SUP) as $role => $map) {
    foreach ($map as $stage => $title) {
        $st = $conn->prepare("UPDATE link_groups SET stage_title=? WHERE owner_role_id=? AND stage_no=?");
        $st->bind_param('sii', $title, $role, $stage);
        $st->execute(); $n3 += $conn->affected_rows; $st->close();
    }
}
echo "  ③ سُمّيت $n3 مجموعةً بأفعالِ الدورةِ المستندية\n";

/* ── ④ أفعالُ السلسلةِ العشرةُ في القاموس ── */
$ACTS = array(
    array('unit.st.03', 'اعتمادُ الموقعِ للقيد (المحطة ٣)', 'Approvals/hours_approval.php'),
    array('unit.st.04', 'مطابقةُ الأطرافِ (المحطة ٤)', 'Approvals/hours_approval.php'),
    array('unit.st.05', 'اعتمادُ الموردِ لوحداتِه (المحطة ٥)', 'Approvals/hours_approval.php'),
    array('unit.st.08', 'مطابقةُ العميلِ على الوحدات (المحطة ٨)', 'Contracts/unit_client_match.php'),
    array('unit.st.09', 'قبولُ العميلِ النهائي (المحطة ٩)', 'Contracts/unit_client_match.php'),
    array('unit.stmt.client', 'كشفُ وحداتِ العميلِ النطاقي', 'Contracts/unit_statement_client.php'),
    array('unit.stmt.supplier', 'كشفُ وحداتِ الموردِ النطاقي', 'Suppliers/unit_statement_supplier.php'),
    array('ap.shares.allocate', 'توزيعُ حصصِ التغطيةِ على الموردين', 'Operations/shares_coverage.php'),
    array('ap.oblig.generate', 'توليدُ التزاماتِ التغطية', 'Operations/shares_coverage.php'),
    array('sales.gate.pass', 'بوابةُ المبيعاتِ للسلسلة (st.06)', 'Approvals/hours_approval.php'),
);
$n4 = 0;
foreach ($ACTS as [$code, $label, $file]) {
    $bn = basename($file);
    $st2 = $conn->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text, event_name,
         consumers_text, effect_text, reverse_text, live_code, state, guard_verified, guard_evidence,
         idempotency_verified, idempotency_evidence, uat_verified, uat_evidence, write_class, updated_at)
        VALUES (?,?,?,?, 'بحسبِ المحطة', 'unit_entries · unit_approvals', 'UnitChainAdvanced',
                'المبيعات · الموردون · المالية', 'السلسلةُ تتقدم محطةً بقرارٍ مسجَّل', 'إرجاعٌ بسببٍ والرقمُ ثابت',
                ?, 'bound_page', 'yes', 'حارسُ الشاشةِ الحاملةِ + سلطةُ المحطةِ في الخدمة',
                'yes', 'المفتاحُ (القيد×المحطة×الجولة) في unit_approvals', 'pending', '', 'domain_write', NOW())");
    $q = $conn->query("SELECT 1 FROM nav09_action_map WHERE canonical_code='" . $conn->real_escape_string($code) . "'");
    if (!($q && $q->num_rows)) {
        $st2->bind_param('sssss', $code, $label, $label, $bn, $code);
        if ($st2->execute()) { $n4++; }
    }
    $st2->close();
}
echo "  ④ سُجِّل $n4 فعلَ سلسلةٍ في القاموس\n";

/* ── ⑤ القاموسُ ووحدتا الكشفين والمطابقة — التسجيلُ قبلَ الشاشة ── */
$mkScreen = function (string $code, string $name, int $owner, array $viewRoles, int $stage) use ($conn, $one, $navAdd) {
    $mid = (int) $one("SELECT id FROM modules WHERE code='" . $conn->real_escape_string($code) . "'");
    if (!$mid) {
        $st = $conn->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                              VALUES (?, ?, ?, 1, 0, 'fa fa-file-lines', 100)");
        $st->bind_param('ssi', $name, $code, $owner);
        $st->execute(); $mid = (int) $conn->insert_id; $st->close();
    }
    foreach ($viewRoles as $role => $write) {
        $q = $conn->query("SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$mid");
        if (!($q && $q->num_rows)) {
            $w = $write ? 1 : 0;
            $conn->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                          VALUES ($role, $mid, 1, $w, $w, 0)");
        }
    }
    $gid = (int) $one("SELECT id FROM link_groups WHERE owner_role_id=$owner AND stage_no=$stage AND group_code LIKE 'n9%' ORDER BY display_order LIMIT 1");
    if (!$gid) {
        $gc = 'n9o_' . preg_replace('/\W+/', '_', strtolower(basename($code, '.php'))) . '_r' . $owner;
        $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                      VALUES ('" . $conn->real_escape_string($name) . "', '$gc', $owner, 'fa fa-file-lines', 80, $stage, '', 1)");
        $gid = (int) $conn->insert_id;
    }
    $navAdd($owner, $code, $name, $gid, $mid);
    return $mid;
};
$mkScreen('Contracts/unit_client_match.php', 'مطابقةُ العميلِ على الوحدات', 12, array(12 => true, 1 => false), 5);
$mkScreen('Contracts/unit_statement_client.php', 'كشفُ وحداتِ العميل', 12, array(12 => false, 1 => false, 17 => false), 6);
$mkScreen('Suppliers/unit_statement_supplier.php', 'كشفُ وحداتِ المورد', 2, array(2 => false, 8 => false, 17 => false), 5);
$mkScreen('main/glossary.php', 'قاموسُ المبتدئ — بلغةِ أولِ يوم', 12, array(12 => false, 2 => false, 6 => false, 1 => false), 98);
/* روابطُ القاموسِ للأدوارِ الأخرى */
$mGl = (int) $one("SELECT id FROM modules WHERE code='main/glossary.php'");
foreach (array(2, 6) as $role) {
    $gid = (int) $one("SELECT id FROM link_groups WHERE owner_role_id=$role AND stage_no=98 AND group_code LIKE 'n9%' ORDER BY display_order LIMIT 1");
    if (!$gid) {
        $conn->query("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                      VALUES ('عونُ المبتدئ', 'n9o_glossary_r$role', $role, 'fa fa-book-open', 95, 98, 'عونُ المبتدئ', 1)");
        $gid = (int) $conn->insert_id;
    }
    $navAdd($role, 'main/glossary.php', 'قاموسُ المبتدئ', $gid, $mGl);
}
echo "  ⑤ سُجِّلت الشاشاتُ الأربعُ بوحداتِها ومنحِها وروابطِها\n";
echo "\n✔ تمّت\n";
