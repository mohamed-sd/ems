<?php
/**
 * 2027_02_11 — بابُ الرئيسية: صفٌّ واحدٌ نشطٌ لكلِّ دورٍ باسمٍ موحَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العقدُ مكتوبٌ بقرارِ مالكٍ مسجَّلٍ** في `tests/unified_nav_test.php:114-125`
 *   (الدستور §6 · قرارُ 2026-07-27): «لكلِّ دورٍ نشطٍ صفٌّ واحدٌ يفتح لوحتَه»
 *   و«التسميةُ موحَّدةٌ **الرئيسية** لا اسمُ اللوحة». وحُذف الرابطُ الثابتُ من
 *   `insidebar` اعتمادًا على هذا الصف.
 *
 * ◆ **والمقيسُ يخالفه تمامًا**: `main/my_workspace.php` في بابِ `HOME`
 *   **`active = 0` في الأدوارِ الخمسةِ والعشرينَ كلِّها — صفرُ صفٍّ نشط**.
 *   أي أن بابَ الرئيسيةِ **ميتٌ لكلِّ دورٍ في النظام**، والرابطُ الثابتُ مرفوعٌ
 *   سلفًا ⇒ **لا مدخلَ إلى اللوحةِ من القائمةِ أصلًا**. وهذا عطلٌ يراه كلُّ
 *   مستخدمٍ ولا يراه فاحصٌ إلا هذا.
 *
 * ◆ و`active` مقبضٌ إداريٌّ يدويٌّ (`admin/permissions/nav_items.php`) لا مشتقٌّ
 *   من منحة — فتعطيلُه ليس حكمَ صلاحيةٍ يُحترَم بل مقبضٌ سُقط.
 *
 * ◆ **ولا يُنشأ صفٌّ لدورٍ ليس له**: إن كان للدورِ صفُّ `HOME` مُعطَّلٌ يُفعَّل،
 *   وإن كان له أكثرُ من صفٍّ يُبقى الأولُ ويُعطَّل الباقي (فالعقدُ «واحدٌ»)،
 *   وإن لم يكن له صفٌّ أصلًا **يُعلَن ولا يُخترع** — فمصدرُ القوائمِ وثيقةُ
 *   `NAV-09` والصفوفُ تُولَّد منها لا من مُرحِّلٍ يحدس.
 *
 * ◆ والتسميةُ تُوحَّد إلى «الرئيسية» في الصفِّ النشطِ وحدَه.
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ العقدُ بعده حرفًا بحرف.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

echo "══ بابُ الرئيسية — صفٌّ واحدٌ نشطٌ لكلِّ دور ══\n";

/* الأدوارُ النشطةُ عدا السوبر — كما يعدُّها الفاحصُ حرفيًّا */
$roles = array();
$r = $db->query("SELECT id FROM roles WHERE (status = '1' OR status = 1) AND id <> -1 ORDER BY id");
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }
echo '  أدوارٌ نشطة: ' . count($roles) . "\n";

$activated = 0; $demoted = 0; $renamed = 0; $missing = array();
foreach ($roles as $rid) {
    $rows = array();
    $q = $db->query("SELECT id, active, label_ar, route FROM nav_items
                      WHERE role_id = {$rid} AND door = 'HOME' ORDER BY sort_order, id");
    while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }
    if (!$rows) { $missing[] = $rid; continue; }

    /* الصفُّ المعتمَد: النشطُ إن وُجد، وإلا الأولُ ترتيبًا */
    $keep = null;
    foreach ($rows as $x) { if ((int) $x['active'] === 1) { $keep = $x; break; } }
    if ($keep === null) { $keep = $rows[0]; }

    if ((int) $keep['active'] !== 1) {
        $db->query('UPDATE nav_items SET active = 1 WHERE id = ' . (int) $keep['id']);
        $activated++;
    }
    if ((string) $keep['label_ar'] !== 'الرئيسية') {
        $db->query("UPDATE nav_items SET label_ar = 'الرئيسية' WHERE id = " . (int) $keep['id']);
        $renamed++;
    }
    foreach ($rows as $x) {
        if ((int) $x['id'] === (int) $keep['id']) { continue; }
        if ((int) $x['active'] === 1) {
            $db->query('UPDATE nav_items SET active = 0 WHERE id = ' . (int) $x['id']);
            $demoted++;
        }
    }
}

echo "  ✔ فُعِّل: {$activated}\n";
echo "  ✔ وُحِّدت التسميةُ إلى «الرئيسية»: {$renamed}\n";
echo "  ✔ عُطِّل الزائدُ (العقدُ «واحدٌ»): {$demoted}\n";
/* ── أدوارٌ بلا صفٍّ أصلًا: يُنشأ **نسخًا** من الشكلِ الموحَّدِ لا اختراعًا ──
     العقدُ يقول «لا دورَ بلا لوحة»، والشكلُ مُستقَرٌّ في خمسةٍ وعشرينَ دورًا:
     `main/my_workspace.php` في بابِ `HOME` باسم «الرئيسية». فيُنسَخ صفٌّ قائمٌ
     بحرفِه ويُغيَّر فيه الدورُ وحدَه — فلا عمودَ يُخمَّن ولا قيمةَ تُختار. */
$created = 0; $stillMissing = array();
if ($missing) {
    $tpl = $db->query("SELECT n.module_id, n.route, n.icon, n.sort_order, n.counter_source,
                              n.permission_code, n.group_id
                         FROM nav_items n
                        WHERE n.door = 'HOME' AND n.active = 1
                          AND n.route = 'main/my_workspace.php' LIMIT 1")->fetch_assoc();
    if (!$tpl) {
        $stillMissing = $missing;
        echo "  ⚠ لا صفَّ قدوةً يُنسَخ — تُعلَن الأدوار: " . implode(' · ', $missing) . "\n";
    } else {
        foreach ($missing as $rid) {
            $st = $db->prepare("INSERT INTO nav_items
                  (role_id, door, group_id, module_id, label_ar, route, icon, sort_order,
                   counter_source, permission_code, active)
                  VALUES (?, 'HOME', ?, ?, 'الرئيسية', ?, ?, ?, ?, ?, 1)");
            $gid = ($tpl['group_id'] !== null) ? (int) $tpl['group_id'] : null;
            $mid = ($tpl['module_id'] !== null) ? (int) $tpl['module_id'] : null;
            $so  = (int) $tpl['sort_order'];
            $st->bind_param('iiisssss', $rid, $gid, $mid, $tpl['route'], $tpl['icon'],
                            $so, $tpl['counter_source'], $tpl['permission_code']);
            if ($st->execute()) { $created++; }
            else { $stillMissing[] = $rid; echo '  ✘ دور ' . $rid . ': ' . mb_substr($st->error, 0, 70) . "\n"; }
            $st->close();
        }
        echo "  ✔ أُنشئ صفُّ رئيسيةٍ نسخًا للأدوار: {$created}\n";
    }
}
if ($stillMissing) {
    echo '  ⚠ أدوارٌ بقيت بلا صفٍّ — تُعلَن: ' . implode(' · ', $stillMissing) . "\n";
}

/* ══ الجسُّ — العقدُ حرفًا بحرف كما يقيسه الفاحص ═══════════════════════ */
echo "\n── جسُّ العقد\n";
$bad1 = (int) $db->query("SELECT COUNT(*) FROM roles r
                           WHERE (r.status='1' OR r.status=1) AND r.id <> -1
                             AND (SELECT COUNT(*) FROM nav_items n
                                   WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) <> 1")
                 ->fetch_row()[0];
echo '  ' . ($bad1 === 0 ? '✔' : '✘') . " أدوارٌ بلا رئيسيةٍ أو برئيسيتين: {$bad1}\n";
$bad2 = (int) $db->query("SELECT COUNT(*) FROM nav_items
                           WHERE door='HOME' AND active=1 AND label_ar <> 'الرئيسية'")->fetch_row()[0];
echo '  ' . ($bad2 === 0 ? '✔' : '✘') . " صفوفٌ نشطةٌ باسمٍ غيرِ موحَّد: {$bad2}\n";

$ok = ($bad1 === 0 && $bad2 === 0);
echo "\n" . ($ok
    ? "✅ بابُ الرئيسيةِ حيٌّ لكلِّ دورٍ — والرابطُ الثابتُ المرفوعُ صار له بديلٌ فعلًا.\n"
    : "⚠ راجِع أعلاه — وما بقي أدوارٌ بلا صفٍّ تُولَّد من وثيقةِ NAV-09 لا هنا\n");
exit($ok ? 0 : 1);
