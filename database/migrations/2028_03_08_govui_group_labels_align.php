<?php
/**
 * 2028_03_08_govui_group_labels_align.php — أسماءُ المجموعاتِ ومواضعُها بعدَ الوصل
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: update:nav_lifecycle_groups(label_ar) + nav_canonical(group_name)
 *                     + nav_route_group(group_code,basis) + log:govui_label_log
 *
 * ◆ **① حاشيةُ `(Overview)` تُنزع من أسماءِ مجموعاتِ الدورة** — ثمانيةَ عشرَ
 *   صفًّا. والدليلُ الجديدُ نزعها من ثمانٍ وثلاثينَ خليّةً، **والنظامُ سبقه
 *   إليها في `link_groups` بقرارِ `FINAL_CLOSE ⑰`** — فبقيت في
 *   `nav_lifecycle_groups` وحدَها. §7: «اسمٌ قديمٌ استُبدل في الملفاتِ الجديدة»
 *   لا يُقبل في الواجهة.
 *
 * ◆ **② مجموعةُ العرضِ للمساراتِ المُعادِ وصلُها** — `nav_canonical.group_name`
 *   ما زال يحمل مجموعةَ **الهدفِ السابقِ** لثلاثةِ مساراتٍ نُقلت في
 *   `2028_03_03`، فيُصيَّر البندُ تحتَ عنوانٍ ليس عنوانَ هدفِه. تُسوّى من
 *   **مجموعةِ هدفِها في الدليل** (`govui_target_registry`).
 *
 * ◆ **③ رأسُ الطيِّ لبندَين وقعا في «التقارير والتحليلات»** — وهما مرحلتان في
 *   دورةِ إدارتَيهما (`التأسيس` · `ب · دخول الأصل`) ومخازنُهما الثلاثةُ صحيحة.
 *   والجذرُ في `nav_route_group`: `group_code='REPORTS'` **و`basis` فارغٌ في
 *   أحدِهما** — إسنادٌ بلا سند. §6 يمنع «مجموعاتٍ عامةً تحلُّ محلَّ دورةِ
 *   الإدارة»، فيُسنَدان إلى تصنيفِ إدارتِهما بسندٍ مكتوب.
 *
 * ⛔ **ولا يُمَسُّ ترتيبٌ ولا يُنزَع رابط** — والتعدادُ المُصيَّرُ يُقاس بعدَها.
 * التشغيل: php database/migrations/2028_03_08_govui_group_labels_align.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$log = $conn->prepare("INSERT INTO govui_label_log
    (target_id, store, store_key, old_label, new_label, source_ref, reason) VALUES (?,?,?,?,?,?,?)");
if (!$log) { exit("⛔ prepare: {$conn->error}\n"); }
$put = function ($tid, $store, $key, $old, $new, $src, $why) use ($log) {
    $log->bind_param('sssssss', $tid, $store, $key, $old, $new, $src, $why);
    $log->execute();
};

/* ═══ ① نزعُ حاشيةِ (Overview) ═══ */
$n1 = 0;
$q = $conn->query("SELECT id, workspace_id, label_ar FROM nav_lifecycle_groups WHERE label_ar LIKE '%(Overview)%'");
$g1 = array(); while ($q && ($x = $q->fetch_assoc())) { $g1[] = $x; }
foreach ($g1 as $x) {
    $new = trim(preg_replace('~\s*\(Overview\)\s*$~u', '', $x['label_ar']));
    $st = $conn->prepare("UPDATE nav_lifecycle_groups SET label_ar = ? WHERE id = ?");
    $st->bind_param('si', $new, $x['id']);
    if (!$st->execute()) { exit("⛔ group {$x['id']}: {$conn->error}\n"); }
    $st->close();
    $put('GRP-' . $x['id'], 'nav_lifecycle_groups.label_ar', (string) $x['id'], $x['label_ar'], $new,
        '01 · الدليل المعماري — نزعُ (Overview) في 38 خليّة', 'اسمٌ قديمٌ استُبدل في الملفِّ الحاكم (§7)');
    $n1++;
}
echo "  ✔ (Overview) نُزع من {$n1} مجموعةَ دورة\n";

/* ═══ ② مجموعةُ العرضِ للمُعادِ وصلُها ═══ */
$n2 = 0;
$q = $conn->query("SELECT DISTINCT w.target_id, r.canonical_group_label, p.route, c.group_name
                     FROM govui_wiring_log w
                     JOIN nav_placements p ON p.target_id = w.target_id
                     JOIN govui_target_registry r ON r.target_id = w.target_id
                     JOIN nav_canonical c ON LOWER(c.route) = LOWER(p.route)
                    WHERE w.new_route IS NOT NULL AND w.new_route <> ''
                      AND w.old_route <> w.new_route
                      AND c.group_name <> r.canonical_group_label");
$g2 = array(); while ($q && ($x = $q->fetch_assoc())) { $g2[] = $x; }
foreach ($g2 as $x) {
    $st = $conn->prepare("UPDATE nav_canonical SET group_name = ? WHERE LOWER(route) = LOWER(?)");
    $st->bind_param('ss', $x['canonical_group_label'], $x['route']);
    if (!$st->execute()) { exit("⛔ canon {$x['route']}: {$conn->error}\n"); }
    $st->close();
    $put($x['target_id'], 'nav_canonical.group_name', $x['route'], $x['group_name'], $x['canonical_group_label'],
        'govui_target_registry — مجموعةُ هدفِه في الدليل', 'المسارُ أُعيد وصلُه فبقيت مجموعةُ هدفِه السابق');
    printf("  ✔ %-16s %s : «%s» ⇐ «%s»\n", $x['target_id'], $x['route'], $x['group_name'], $x['canonical_group_label']);
    $n2++;
}
echo "  ✔ مجموعةُ العرضِ سُوِّيت في {$n2} مسارًا\n";

/* ═══ ③ رأسُ الطيِّ لبندَين وقعا في التقارير — **رُفع ولم يُنفَّذ** ═══
   ◆ **جُرِّب فقيس أثرُه فرُدّ**: أُسند المساران إلى تصنيفِ إدارتَيهما، فانتقل
     `Fleet/asset_use_rights.php` إلى قسمِ «المعدات والأسطول» **فبلغ القسمُ
     عشرةَ عناصرَ** ورسبت بوّابةُ `U9` (الحدُّ تسعةٌ بنصِّ ف٧-٢). فالإسنادُ
     يعالج عَرَضًا ويُحدث عطبًا آخر.
   ◆ **والجذرُ المقيسُ أعمقُ**: كلا المسارَين **سجلٌّ تابعٌ** (`Child Register`
     بنصِّ بطاقتِه) وموضعُه `TAB_CHILD`، **فلا تشمله طبقةُ المواضع** التي تحمل
     مجموعةَ الدورة — وهي تعُدُّ الظاهرَ في القائمةِ وحدَه. وهما مع **تسعةٍ
     وثلاثين سجلًّا تابعًا بمسارٍ مستقلٍّ** يُصيَّرون بنودًا تحتَ تصنيفٍ عامّ.
   ◆ **و§8 يقول**: «لا يظهر في السايدبار إلّا ما يجب أن يظهر فعلًا — خصوصًا
     Child Registers». **ونزعُ تسعةٍ وثلاثين رابطًا حيًّا تغييرُ وصولٍ** لا
     يقرّره فاحصٌ ⇒ يُرفع بحاجزِه المسمّى `OA-11` ويُقيَّد في السجلِّ الجامع.
   ⛔ **فلا يُكتب هنا شيءٌ** — والتقييدُ في الوثيقةِ لا في المخزن. */

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
