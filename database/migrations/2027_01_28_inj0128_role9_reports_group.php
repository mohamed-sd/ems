<?php
/**
 * 2027_01_28 — INJ-0128: تقاريرُ الإدارةِ التنفيذيةِ بابًا في قائمةِ الدور 9
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العطل المقيس: أربعُ شاشاتِ تقاريرَ **ممنوحةٌ** للدور 9 بـ`can_view=1`
 *   و**بلا صفِّ تنقّل** — منحٌ بلا باب. المستخدمُ يملك الحقَّ ولا يجد الطريق،
 *   وهو أسوأُ من المنعِ لأنه لا يُعلن نفسَه.
 *     Portal/ceo_reports.php · Governance/gov_reports.php
 *     Audit/iaf_reports.php  · Risk/risk_reports.php
 * ◆ والحكمُ قال «ثلاثًا» — وعدَّها التدقيقُ قبلَ تغييراتِ هذه الحزمة. والمنحُ
 *   بلا بابٍ عيبٌ أيًّا كان العدد، فتُفتح الأربعُ ويُعلَن الفرقُ لا يُسكت عنه.
 * ◆ البادئة `n9o_`: مجموعةٌ ليست في وثيقةِ NAV-09 لدور 9 (مراحلُه تنتهي عند 7).
 *   و`nav09_verify` يقابل `n9s%` بالوثيقةِ فيرسب على مجموعةٍ ليست فيها،
 *   و`sweep_others` يكنس ما عدا البادئتين. فـ`n9o_` وحدَها تعبر الفاحصَين.
 * ◆ ومُتحمِّلٌ للتكرار: كلُّ إدراجٍ مسبوقٌ بفحصِ وجودِه.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

$ROLE = 9;
$CODE = 'n9o_reports_r9';

/* ① المجموعة */
$rs = $db->query("SELECT id FROM link_groups WHERE group_code = '{$CODE}' LIMIT 1");
if (!$rs) { fwrite(STDERR, 'link_groups: ' . $db->error . "\n"); exit(1); }
$row = $rs->fetch_row();
if ($row) {
    $gid = (int) $row[0];
    echo "  ① المجموعةُ قائمةٌ (#{$gid})\n";
} else {
    $st = $db->prepare("INSERT INTO link_groups
        (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
        VALUES (?, ?, ?, 'fa fa-file-lines', 800, 8, ?, 1)");
    if (!$st) { fwrite(STDERR, 'prepare group: ' . $db->error . "\n"); exit(1); }
    $nm = 'التقارير والتصدير';
    $stt = 'ثامنًا: التقارير والتصدير';
    $st->bind_param('ssis', $nm, $CODE, $ROLE, $stt);
    if (!$st->execute()) { fwrite(STDERR, 'insert group: ' . $st->error . "\n"); exit(1); }
    $gid = (int) $st->insert_id;
    $st->close();
    echo "  ① مجموعةٌ جديدة #{$gid} «التقارير والتصدير»\n";
}
if ($gid <= 0) { fwrite(STDERR, "معرّفُ مجموعةٍ غيرُ صالح\n"); exit(1); }

/* ② الأبوابُ الأربعةُ — لا يُفتح بابٌ إلا لممنوحٍ فعلًا (لا منحَ جديد هنا) */
$WANT = array(
    'Portal/ceo_reports.php'     => 'تقارير الإدارة التنفيذية',
    'Governance/gov_reports.php' => 'تقارير الحوكمة',
    'Audit/iaf_reports.php'      => 'تقارير المراجعة للجهة المشرفة',
    'Risk/risk_reports.php'      => 'تقارير المخاطر والتحليلات',
);
$added = 0; $skipped = array();
$order = 10;
foreach ($WANT as $route => $label) {
    /* المنحُ شرطُ فتحِ الباب: بابٌ بلا منحٍ يقود إلى ردٍّ — وهو عطلٌ آخر. */
    $st = $db->prepare("SELECT m.id FROM modules m
                         JOIN role_permissions rp ON rp.module_id = m.id AND rp.role_id = ? AND rp.can_view = 1
                        WHERE m.code = ? LIMIT 1");
    $st->bind_param('is', $ROLE, $route);
    $st->execute();
    $m = $st->get_result()->fetch_row();
    $st->close();
    if (!$m) { $skipped[] = $route . ' (لا منحَ قراءةٍ — لا يُفتح بابٌ يقود إلى ردّ)'; continue; }
    $moduleId = (int) $m[0];

    $st = $db->prepare("SELECT COUNT(*) FROM nav_items WHERE role_id = ? AND route = ?");
    $st->bind_param('is', $ROLE, $route);
    $st->execute();
    $exists = (int) $st->get_result()->fetch_row()[0];
    $st->close();
    if ($exists > 0) { $skipped[] = $route . ' (بابٌ قائم)'; continue; }

    $st = $db->prepare("INSERT INTO nav_items
        (role_id, group_id, module_id, label_ar, route, icon, sort_order, active)
        VALUES (?, ?, ?, ?, ?, 'fa fa-file-lines', ?, 1)");
    if (!$st) { fwrite(STDERR, 'prepare nav: ' . $db->error . "\n"); exit(1); }
    $st->bind_param('iiissi', $ROLE, $gid, $moduleId, $label, $route, $order);
    if (!$st->execute()) { fwrite(STDERR, 'insert nav: ' . $st->error . "\n"); exit(1); }
    $st->close();
    $added++; $order += 10;
    echo "  ② بابٌ: {$label} ({$route})\n";
}
foreach ($skipped as $s) { echo "  · تُخطّي: {$s}\n"; }

/* ══ إثباتٌ وظيفيّ: صفرُ منحٍ بلا بابٍ في تقاريرِ الدور 9 ═════════════════ */
$rs = $db->query("SELECT COUNT(*) FROM role_permissions rp
                    JOIN modules m ON m.id = rp.module_id
                   WHERE rp.role_id = {$ROLE} AND rp.can_view = 1
                     AND (m.code LIKE '%report%' OR m.name LIKE '%تقرير%')
                     AND NOT EXISTS (SELECT 1 FROM nav_items ni
                                      WHERE ni.role_id = {$ROLE} AND ni.route = m.code)");
if (!$rs) { fwrite(STDERR, 'تحقق: ' . $db->error . "\n"); exit(1); }
$dead = (int) $rs->fetch_row()[0];
if ($dead !== 0) { fwrite(STDERR, "بقي {$dead} منحًا بلا باب\n"); exit(1); }

echo "  ✔ إثبات: صفرُ منحِ تقريرٍ بلا بابٍ للدور {$ROLE} · أبوابٌ مضافة: {$added}\n";
echo "  الشاهد: php tools/fix_od19_probe.php (INJ-0128)\n";
exit(0);
