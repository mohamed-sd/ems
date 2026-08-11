<?php
/**
 * 2027_01_31 — INJ-0494: مقامُ «الشاشة» واحدٌ — وفرقان حقيقيان يُسدّان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أداةُ `tools/screen_registry_gate.php` قابلت **الأسطحَ الحيةَ** (ما يُضمِّن
 *   `insidebar.php`) بسجلِّ `modules` فأخرجت الفرقَ في الاتجاهين. وبعد استثناءِ
 *   `includes/` و`examples/` (أغلفةٌ يُضمِّنها غيرُها لا شاشاتٌ تُبلَغ بمسار)
 *   بقي **فرقان حقيقيان** — وكلاهما عطلٌ بعينه لا خلافُ تعريف:
 *
 * ① **`Equipments/select_project.php` شاشةٌ حيةٌ غيرُ مسجَّلة.**
 *    تفتح الجلسةَ وتُضمِّن `config` و`inheader` و`insidebar` ولها `page_title` —
 *    و**خمسةُ أسطحِ إنتاجٍ تقود إليها** (`equipments.php` · `role_board.php` ·
 *    `move_oprators.php` · `oprators.php` · `ContainerGate`). ولأنها غيرُ
 *    مسجَّلةٍ يُرجع حارسُ الشاشةِ `_deny_all_permissions('unresolved_script_path')`
 *    ⇒ **كلُّ من نقر رابطَها يُمنع**. رابطٌ حيٌّ إلى بابٍ مغلقٍ بنيويًّا.
 *    والمنحُ يُعطى **لاتحادِ أدوارِ الأسطحِ التي تقود إليها** — فمن بلغ الرابطَ
 *    يبلغ الشاشة، ولا يُمنح غيرُهم.
 *
 * ② **الوحدةُ #230 `Tickets/dept` نسخةٌ مبتورةٌ من #307.**
 *    اسمُها «بلاغاتُ إدارتي» ورمزُها مبتورٌ عند `Tickets/dept` (والصحيحُ
 *    `Tickets/dept_inbox.php` = 26 محرفًا في عمودٍ يقبل 50 — فالبترُ ليس ضيقَ
 *    عمود). **بصفرِ منحةٍ**، و**صفّا تنقّلٍ يشيران إليها** بمسارٍ صحيحٍ ووحدةٍ
 *    خاطئة — أحدُهما نشطٌ للدور 11. فالمستخدمُ يرى البابَ ويُمنع عند الدخول.
 *    والصحيحةُ #307 لها **28 منحة**.
 *    ◆ **ولا يُصحَّح الرمزُ إلى `Tickets/dept_inbox.php`**: ذلك يُنتج **رمزَين
 *      متطابقَين**، وحلَّالُ الوحدةِ يأخذ **أدنى id** (گوتشا مسجَّلة) فتغلب
 *      #230 ذاتُ الصفرِ منحةً على #307 ذاتِ الثمانِ والعشرين ⇒ **إغلاقٌ تامٌّ
 *      لشاشةٍ تعمل**. فالصوابُ إعادةُ توجيهِ الصفَّين ثم رفعُ الصفِّ المبتور.
 *
 * ◆ ويُطبَع كلُّ ما سيُمسّ **قبل** مسِّه — فالرفعُ بعد إعلانٍ لا قبله.
 * ◆ مُتحمِّلٌ للتكرار.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$ROOT = dirname(__DIR__, 2);
$q1 = static function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row() : null; };

/* ══ ① تسجيلُ الشاشةِ الحيةِ غيرِ المسجَّلة ══════════════════════════════ */
$SCREEN = 'Equipments/select_project.php';
echo "① {$SCREEN}\n";
if (!is_file($ROOT . '/' . $SCREEN)) { fwrite(STDERR, "الملفُّ غيرُ موجود — لا يُسجَّل مجهول\n"); exit(1); }
$row = $q1("SELECT id FROM modules WHERE code = '" . $db->real_escape_string($SCREEN) . "'");
if ($row) {
    $modId = (int) $row[0];
    echo "    · مسجَّلٌ سلفًا #{$modId}\n";
} else {
    $st = $db->prepare("INSERT INTO modules (name, code, owner_role_id, group_id, is_link, is_quick, icon, display_order)
                        VALUES (?, ?, NULL, NULL, '0', 0, 'fa fa-diagram-project', 995)");
    if (!$st) { fwrite(STDERR, 'prepare module: ' . $db->error . "\n"); exit(1); }
    $nm = 'اختيار المشروع';
    $st->bind_param('ss', $nm, $SCREEN);
    if (!$st->execute()) { fwrite(STDERR, 'insert module: ' . $st->error . "\n"); exit(1); }
    $modId = (int) $st->insert_id;
    $st->close();
    echo "    ✔ سُجِّلت وحدةً #{$modId}\n";
}

/* المنحُ لاتحادِ أدوارِ الأسطحِ التي تقود إليها — لا أوسعَ ولا أضيق */
$LINKERS = array('Equipments/equipments.php', 'movement/move_oprators.php', 'Oprators/oprators.php');
$roles = array();
foreach ($LINKERS as $code) {
    $r = $q1("SELECT GROUP_CONCAT(rp.role_id) FROM role_permissions rp
               JOIN modules m ON m.id = rp.module_id
              WHERE m.code = '" . $db->real_escape_string($code) . "' AND rp.can_view = 1");
    if (!$r || $r[0] === null) { echo "    · {$code}: بلا منحٍ — تُخطّى\n"; continue; }
    foreach (explode(',', (string) $r[0]) as $x) { $x = (int) trim($x); if ($x !== 0) { $roles[$x] = 1; } }
}
$roles = array_keys($roles);
sort($roles);
echo '    · اتحادُ أدوارِ المُشيرين (' . count($roles) . '): ' . implode('، ', $roles) . "\n";
if (!$roles) { fwrite(STDERR, "صفرُ دورٍ — لا تُسجَّل شاشةٌ بلا منحٍ (AC-F1b)\n"); exit(1); }
$granted = 0;
foreach ($roles as $r) {
    $ex = $q1("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$modId} AND role_id = {$r}");
    if ($ex && (int) $ex[0] > 0) { continue; }
    if (!$db->query("INSERT INTO role_permissions (module_id, role_id, can_view) VALUES ({$modId}, {$r}, 1)")) {
        fwrite(STDERR, 'منح ' . $r . ': ' . $db->error . "\n"); exit(1);
    }
    $granted++;
}
echo "    ✔ منحُ قراءةٍ مضافةٌ: {$granted} (والقائمُ لا يُكرَّر)\n";

/* ══ ② الوحدةُ المبتورةُ #230 ═════════════════════════════════════════ */
echo "\n② الوحدةُ المبتورة `Tickets/dept`\n";
$bad = $q1("SELECT id FROM modules WHERE code = 'Tickets/dept'");
if (!$bad) {
    echo "    · لا وجودَ لها — مُعالَجةٌ سلفًا\n";
} else {
    $badId = (int) $bad[0];
    $good = $q1("SELECT id FROM modules WHERE code = 'Tickets/dept_inbox.php'");
    if (!$good) { fwrite(STDERR, "الوحدةُ الصحيحةُ غيرُ موجودة — لا يُعاد توجيهٌ إلى مجهول\n"); exit(1); }
    $goodId = (int) $good[0];
    $gr = $q1("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$badId}");
    $grOk = $q1("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$goodId}");
    echo "    · المبتورة #{$badId} بمنحٍ: " . (int) $gr[0] . " · الصحيحة #{$goodId} بمنحٍ: " . (int) $grOk[0] . "\n";
    if ((int) $gr[0] !== 0) {
        fwrite(STDERR, "المبتورةُ تحمل منحًا — لا تُرفع حتى يُحسم مصيرُها بقرار\n"); exit(1);
    }
    /* إعلانُ كلِّ صفٍّ سيُمسّ **قبل** مسِّه */
    $rs = $db->query("SELECT id, role_id, label_ar, route, active FROM nav_items WHERE module_id = {$badId}");
    $navIds = array();
    while ($rs && ($x = $rs->fetch_assoc())) {
        $navIds[] = (int) $x['id'];
        echo "    · صفُّ تنقّلٍ #{$x['id']} (دور {$x['role_id']} · نشط={$x['active']}) → {$x['route']}\n";
    }
    if ($navIds) {
        if (!$db->query("UPDATE nav_items SET module_id = {$goodId} WHERE module_id = {$badId}")) {
            fwrite(STDERR, 'إعادةُ التوجيه: ' . $db->error . "\n"); exit(1);
        }
        echo '    ✔ أُعيد توجيهُ ' . count($navIds) . " صفًّا إلى #{$goodId} (المسارُ كان صحيحًا والوحدةُ خاطئة)\n";
    }
    if (!$db->query("DELETE FROM modules WHERE id = {$badId}")) {
        fwrite(STDERR, 'رفعُ المبتورة: ' . $db->error . "\n"); exit(1);
    }
    echo "    ✔ رُفعت الوحدةُ المبتورةُ #{$badId} بعد إعلانِ كلِّ ما مسَّته\n";
}

/* ══ إثباتٌ وظيفيّ ═════════════════════════════════════════════════════ */
echo "\n③ الإثبات:\n";
$m = $q1("SELECT COUNT(*) FROM modules WHERE code = '" . $db->real_escape_string($SCREEN) . "'");
$g = $q1("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
           WHERE m.code = '" . $db->real_escape_string($SCREEN) . "' AND rp.can_view = 1");
echo '    · ' . $SCREEN . ' مسجَّلة: ' . (int) $m[0] . ' · بمنحِ قراءةٍ: ' . (int) $g[0] . "\n";
$d = $q1("SELECT COUNT(*) FROM modules WHERE code = 'Tickets/dept'");
echo '    · `Tickets/dept` باقيةٌ: ' . (int) $d[0] . "\n";
$orph = $q1("SELECT COUNT(*) FROM nav_items ni
              WHERE ni.module_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM modules m WHERE m.id = ni.module_id)");
echo '    · صفوفُ تنقّلٍ تشير إلى وحدةٍ غيرِ موجودة: ' . (int) $orph[0] . "\n";
if ((int) $m[0] !== 1 || (int) $g[0] < 1 || (int) $d[0] !== 0 || (int) $orph[0] !== 0) {
    fwrite(STDERR, "الإثباتُ لم يستوفِ شرطَه\n"); exit(1);
}
echo "\n  الشاهد: php tools/screen_registry_gate.php\n";
exit(0);
