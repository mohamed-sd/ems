<?php
/**
 * tests/containers_structure_test.php — H-01 المرحلة ①
 * ═══════════════════════════════════════════════════════════════════════════
 * بنيةُ الحاويات وقيودُها **بلا سلوك** (OPM-01 §4).
 *
 * ما يُثبته:
 *   ① الجداولُ الأربعةُ قائمةٌ ومسجَّلةٌ في بوابة العزل (وإلا رفضتها fail-closed).
 *   ② **صفرُ أثرٍ على المسار الحي**: الجداولُ فارغةٌ ولا شيءَ يقرؤها بعد.
 *   ③ **قيدُ Σ بنيويٌّ لا فحصٌ تطبيقي**: تخصيصٌ يتجاوز السقفَ **ترفضه القاعدة**
 *      — ويُفحص على المستويات الأربعة لا على واحد.
 *   ④ والاستهلاكُ كذلك: لا يُستهلك أكثرُ من السقف.
 *   ⑤ **الشجرةُ لا تُعلَّق في الهواء**: رئيسيةٌ بأبٍ مرفوضة، وفرعٌ بلا أبٍ مرفوض.
 *   ⑥ **الرئيسيةُ من بند العقد لا من اليد**: بندٌ واحدٌ ⇒ رئيسيةٌ واحدة.
 *   ⑦ **عطالةُ الاستهلاك**: المفتاحُ نفسُه لا يخصم مرتين.
 *   ⑧ `remaining_qty` **مولَّدٌ لا يُكتب** — فلا مصدرانِ للرقم الواحد.
 *   ⑨ تبديلٌ بلا تبديلٍ مرفوض · ودورةٌ بلا يومِ عملٍ مرفوضة.
 *   ⑩ **الذريّةُ**: معاملةٌ تتجاوز السقفَ في أحد مستوياتها تُلغى **كلُّها**.
 *
 * كلُّ ما يُنشأ يُكنس. لا يمسّ عقدًا ولا صفَّ دوامٍ ولا يغيّر سلوكًا قائمًا.
 * التشغيل: php tests/containers_structure_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'containers test');
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';

use App\Core\TenantRegistry;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$CO = 4;
$MARK = 'CNTT' . getmypid();

$cleanup = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM operator_rotations WHERE container_id IN
                    (SELECT id FROM (SELECT id FROM op_containers WHERE container_no LIKE '{$MARK}%') x)");
    $conn->query("DELETE FROM container_swaps WHERE container_id IN
                    (SELECT id FROM (SELECT id FROM op_containers WHERE container_no LIKE '{$MARK}%') x)");
    $conn->query("DELETE FROM container_consumption WHERE container_id IN
                    (SELECT id FROM (SELECT id FROM op_containers WHERE container_no LIKE '{$MARK}%') x)");
    // الأبناءُ قبل الآباء — FK بـRESTRICT
    for ($i = 0; $i < 4; $i++) {
        $conn->query("DELETE FROM op_containers WHERE container_no LIKE '{$MARK}%'
                        AND id NOT IN (SELECT parent_id FROM (SELECT parent_id FROM op_containers
                                        WHERE parent_id IS NOT NULL) p)");
    }
};
$cleanup();
register_shutdown_function($cleanup);

/** إدراجٌ يُرجع رسالةَ الخطأ بدل أن يرمي — لاختبار القيود. */
function tryQ($conn, $sql) {
    try { return $conn->query($sql) ? '' : $conn->error; }
    catch (\Throwable $t) { return $t->getMessage(); }
}

fwrite(STDOUT, "\n══ H-01 المرحلة ① — البنيةُ والقيودُ بلا سلوك ══\n");

// ═══ ① الجداولُ ومسجَّليّتُها ═══
head('① الجداولُ الأربعةُ ومسجَّليّتُها في بوابة العزل');
$TABLES = array('op_containers', 'container_consumption', 'container_swaps', 'operator_rotations');
foreach ($TABLES as $t) {
    check($conn->query("SHOW TABLES LIKE '{$t}'")->num_rows === 1, "الجدولُ `{$t}` قائم");
    $def = TenantRegistry::get($t);
    check(is_array($def) && $def['type'] === TenantRegistry::T_TENANT,
        "  ومسجَّلٌ T_TENANT — والجدولُ غيرُ المسجَّل ترفضه البوابةُ fail-closed");
}

// ═══ ② صفرُ أثرٍ على المسار الحي ═══
head('② صفرُ أثرٍ على المسار الحي');
// ⚠️ حُدِّث بالمرحلة ② (2026-07-29): كان هنا «الجداولُ فارغة» — وهو تأكيدٌ
// صحيحٌ في ① **نقضته ②** حين وُلّدت حاوياتٌ حقيقية. والباقي صحيحًا وجوهريًّا
// هو **صفرُ قارئٍ في مسار الإدخال والاعتماد**: الحجبُ للمرحلة ③.
$rows = 0;
foreach ($TABLES as $t) { $rows += (int) $conn->query("SELECT COUNT(*) c FROM `{$t}`")->fetch_assoc()['c']; }
info("صفوفُ الحاويات (وُلّدت في المرحلة ②): {$rows}");
$ts = (int) $conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$ue = (int) $conn->query("SELECT COUNT(*) c FROM unit_entries")->fetch_assoc()['c'];
info("والمسارُ الحيُّ كما هو: {$ts} صفَّ دوامٍ · {$ue} واقعة");
$readers = 0;
foreach (array('Timesheet/timesheet.php', 'app/Services/Unit/TimesheetEntryService.php',
               'Approvals/hours_approval_handler.php') as $f) {
    $src = @file_get_contents(dirname(__DIR__) . '/' . $f);
    if ($src !== false && mb_strpos($src, 'op_containers') !== false) { $readers++; }
}
check($readers === 0, "وصفرُ قارئٍ في مسار الإدخال والاعتماد: {$readers} — الحجبُ للمرحلة ③");

// ── بذرةُ شجرةٍ كاملة ────────────────────────────────────────────────────
head('البذرة — شجرةٌ من أربعة مستويات');
$CID = (int) $conn->query("SELECT id FROM contracts WHERE company_id={$CO}
                            AND contract_status IN ('نافذ','قيد التنفيذ') LIMIT 1")->fetch_assoc()['id'];
$ITEM = $conn->query("SELECT id FROM contractequipments WHERE contract_id={$CID} LIMIT 1")->fetch_assoc();
$ITEM_ID = $ITEM ? (int) $ITEM['id'] : null;
$itemSql = ($ITEM_ID === null) ? 'NULL' : $ITEM_ID;

$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, contract_item_id, unit_type, cap_qty, project_id)
        VALUES ({$CO}, '{$MARK}-M', 'رئيسية', NULL, {$CID}, {$itemSql}, 'hour', 1000.00, 1)");
check($e === '', 'الرئيسيةُ بسقف 1000: ' . ($e ?: 'أُنشئت'));
$MAIN = (int) $conn->insert_id;

$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, unit_type, cap_qty, supplier_id)
        VALUES ({$CO}, '{$MARK}-S', 'مورد', {$MAIN}, {$CID}, 'hour', 600.00, 1)");
check($e === '', 'وحاويةُ موردٍ بـ600 تحتها');
$SUP = (int) $conn->insert_id;
$conn->query("UPDATE op_containers SET allocated_qty=600.00 WHERE id={$MAIN}");

$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, unit_type, cap_qty, equipment_id, role_kind)
        VALUES ({$CO}, '{$MARK}-E', 'معدة', {$SUP}, {$CID}, 'hour', 400.00, 12, 'أساسية')");
check($e === '', 'ومعدةٌ بـ400 تحته');
$EQ = (int) $conn->insert_id;
$conn->query("UPDATE op_containers SET allocated_qty=400.00 WHERE id={$SUP}");

$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, unit_type, cap_qty, operator_employee_id, role_kind, shift_no)
        VALUES ({$CO}, '{$MARK}-O', 'مشغّل', {$EQ}, {$CID}, 'hour', 250.00, 3, 'أساسي', 1)");
check($e === '', 'ومشغّلٌ بـ250 تحتها');
$OP = (int) $conn->insert_id;
$conn->query("UPDATE op_containers SET allocated_qty=250.00 WHERE id={$EQ}");
info("الشجرة: رئيسية#{$MAIN}(1000) ← مورد#{$SUP}(600) ← معدة#{$EQ}(400) ← مشغّل#{$OP}(250)");

// ═══ ③ قيدُ Σ على المستويات الأربعة ═══
head('③ قيدُ Σ بنيويٌّ — على المستويات الأربعة لا على واحد');
foreach (array(array($MAIN, 1000, 'الرئيسية'), array($SUP, 600, 'المورد'),
               array($EQ, 400, 'المعدة'), array($OP, 250, 'المشغّل')) as $lvl) {
    list($id, $cap, $name) = $lvl;
    $over = $cap + 0.01;
    $e = tryQ($conn, "UPDATE op_containers SET allocated_qty={$over} WHERE id={$id}");
    check($e !== '' && stripos($e, 'ck_container_alloc') !== false,
        "{$name}: تخصيصُ {$over} فوق سقف {$cap} **ترفضه القاعدة**");
}
$e = tryQ($conn, "UPDATE op_containers SET allocated_qty=-1 WHERE id={$MAIN}");
check($e !== '', 'وتخصيصٌ سالبٌ مرفوض');

// ═══ ④ الاستهلاك ═══
head('④ لا يُستهلك أكثرُ من السقف');
$e = tryQ($conn, "UPDATE op_containers SET consumed_qty=250.00 WHERE id={$OP}");
check($e === '', 'استهلاكُ 250 من 250: مقبول');
$e = tryQ($conn, "UPDATE op_containers SET consumed_qty=250.01 WHERE id={$OP}");
check($e !== '' && stripos($e, 'ck_container_consumed') !== false,
    'و250.01: **ترفضه القاعدة** — ' . mb_substr($e, 0, 60));
$conn->query("UPDATE op_containers SET consumed_qty=100.00 WHERE id={$OP}");

// ═══ ⑧ المولَّد ═══
head('⑧ `remaining_qty` مولَّدٌ لا يُكتب');
$r = $conn->query("SELECT cap_qty, consumed_qty, remaining_qty FROM op_containers WHERE id={$OP}")->fetch_assoc();
check((float) $r['remaining_qty'] == 150.00, 'المتبقي محسوبٌ من القاعدة: ' . $r['remaining_qty']);
/* ◆ **المضمونُ أن الرقمَ لا يُفرَض — لا أن المحرِّكَ يصيح.** كان الحكمُ «الكتابةُ
     تُردُّ بخطأ»، و**القياسُ يقول غيرَ ذلك**: `sql_mode` في هذا النظامِ **فارغٌ**،
     فإسنادُ قيمةٍ إلى عمودٍ مولَّدٍ يُخفَّض من خطأٍ (1906) إلى **تحذيرٍ** ويُهمَل
     الإسناد — `errno = 0` و`warning_count = 1`. فالفاحصُ كان يطلب صياحًا لا
     يصيحه المحرِّكُ بهذه التهيئة، ويُدين ضمانةً **قائمةً**.
   ⇒ يُحكَم على الضمانةِ نفسِها: القيمةُ **لا تتغيّر** بعد محاولةِ الكتابة، فتبقى
     مشتقّةً من `cap − consumed` وحدَه. وهذا حكمٌ يصحُّ على المحرِّكَين: إن صاح
     رُدَّت الكتابةُ، وإن أهمل بقيت القيمة. ويُعلَن التحذيرُ لأنه الدليلُ على أن
     الإسنادَ **لم يُطبَّق** لا على أنه نجح. */
$before = $conn->query("SELECT remaining_qty FROM op_containers WHERE id={$OP}")->fetch_assoc();
$e = tryQ($conn, "UPDATE op_containers SET remaining_qty=999 WHERE id={$OP}");
$after = $conn->query("SELECT remaining_qty FROM op_containers WHERE id={$OP}")->fetch_assoc();
check((float) $after['remaining_qty'] == (float) $before['remaining_qty']
      && (float) $after['remaining_qty'] != 999.00,
    'والكتابةُ إليه لا تُغيّره — فلا مصدرانِ للرقم الواحد ('
    . ($e !== '' ? 'رُدَّت بخطأ' : 'أُهملت بتحذيرٍ · sql_mode فارغ') . ': '
    . $before['remaining_qty'] . ' ⇒ ' . $after['remaining_qty'] . ')');

// ═══ ⑤ الشجرةُ لا تُعلَّق في الهواء ═══
head('⑤ الشجرةُ لا تُعلَّق في الهواء');
$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, unit_type, cap_qty) VALUES ({$CO}, '{$MARK}-X1', 'رئيسية', {$MAIN}, {$CID}, 'hour', 10)");
check($e !== '' && stripos($e, 'ck_container_parent') !== false, 'رئيسيةٌ بأبٍ: مرفوضة');
$e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
        contract_id, unit_type, cap_qty) VALUES ({$CO}, '{$MARK}-X2', 'مورد', NULL, {$CID}, 'hour', 10)");
check($e !== '' && stripos($e, 'ck_container_parent') !== false, 'وفرعٌ بلا أب: مرفوض');
$e = tryQ($conn, "DELETE FROM op_containers WHERE id={$MAIN}");
check($e !== '', 'وأبٌ له أبناء: لا يُحذف (FK RESTRICT)');

// ═══ ⑥ الرئيسيةُ من البند لا من اليد ═══
head('⑥ الرئيسيةُ مصدرُها بندُ العقد');
if ($ITEM_ID !== null) {
    $e = tryQ($conn, "INSERT INTO op_containers (company_id, container_no, level, parent_id,
            contract_id, contract_item_id, unit_type, cap_qty)
            VALUES ({$CO}, '{$MARK}-M2', 'رئيسية', NULL, {$CID}, {$ITEM_ID}, 'hour', 500)");
    check($e !== '' && stripos($e, 'uq_main_per_item') !== false,
        'بندٌ واحدٌ ⇒ رئيسيةٌ واحدةٌ لا اثنتان');
} else { ok('(العقدُ بلا بنودٍ — يُتخطّى)'); }

// ═══ ⑦ عطالةُ الاستهلاك ═══
head('⑦ عطالةُ الاستهلاك — المفتاحُ لا يخصم مرتين');
$e = tryQ($conn, "INSERT INTO container_consumption (company_id, container_id, source_kind,
        source_ref, qty, unit_type, consumed_on, idem_key)
        VALUES ({$CO}, {$OP}, 'unit_entry', 918, 8.00, 'hour', '2027-01-01', '{$MARK}:918')");
check($e === '', 'خصمٌ أولُ مقبول');
$e = tryQ($conn, "INSERT INTO container_consumption (company_id, container_id, source_kind,
        source_ref, qty, unit_type, consumed_on, idem_key)
        VALUES ({$CO}, {$OP}, 'unit_entry', 918, 8.00, 'hour', '2027-01-01', '{$MARK}:918')");
check($e !== '' && stripos($e, 'uq_consumption_idem') !== false,
    'والمفتاحُ نفسُه مرةً ثانية: **ترفضه القاعدة** — لا تكرارَ استهلاك');
$e = tryQ($conn, "INSERT INTO container_consumption (company_id, container_id, source_kind,
        source_ref, qty, unit_type, consumed_on, idem_key)
        VALUES ({$CO}, {$OP}, 'unit_entry', 918, -8.00, 'hour', '2027-01-02', '{$MARK}:918:rev')");
check($e === '', 'وردٌّ سالبٌ بمفتاحٍ آخر مقبول — العكسُ حركةٌ موثَّقة');

// ═══ ⑨ التبديلُ والدورة ═══
head('⑨ تبديلٌ بلا تبديل · ودورةٌ بلا عمل');
$e = tryQ($conn, "INSERT INTO container_swaps (company_id, container_id, swap_kind, out_ref,
        in_ref, effective_from, reason) VALUES ({$CO}, {$OP}, 'مشغّل', 5, 5, '2027-01-01', 'س')");
check($e !== '' && stripos($e, 'ck_swap_differs') !== false, 'الداخلُ هو الخارج: مرفوض');
$e = tryQ($conn, "INSERT INTO container_swaps (company_id, container_id, swap_kind, out_ref,
        in_ref, effective_from, reason) VALUES ({$CO}, {$OP}, 'مشغّل', 5, 6, '2027-01-01', 'إجازةٌ اضطرارية')");
check($e === '', 'وتبديلٌ حقيقيٌّ بسببه: مقبول');
$e = tryQ($conn, "INSERT INTO operator_rotations (company_id, container_id, operator_employee_id,
        cycle_on_days, cycle_off_days, cycle_start) VALUES ({$CO}, {$OP}, 3, 0, 7, '2027-01-01')");
check($e !== '' && stripos($e, 'ck_rotation_cycle') !== false, 'ودورةٌ بصفر يومِ عمل: مرفوضة');
$e = tryQ($conn, "INSERT INTO operator_rotations (company_id, container_id, operator_employee_id,
        cycle_on_days, cycle_off_days, cycle_start) VALUES ({$CO}, {$OP}, 3, 21, 7, '2027-01-01')");
check($e === '', 'ودورةُ 21/7: مقبولة');

// ═══ ⑩ الذريّة ═══
head('⑩ الذريّةُ — معاملةٌ تتجاوز في أحد مستوياتها تُلغى كلُّها');
$before = $conn->query("SELECT allocated_qty FROM op_containers WHERE id={$SUP}")->fetch_assoc();
$conn->begin_transaction();
$okAll = true;
// ⚠️ گوتشا مثبَتة: `config.php` يضبط تقاريرَ mysqli على **عدم الرمي**، فالاستعلامُ
// المخالفُ يعود `false` **صامتًا** ولا يرمي استثناءً. فمَن لفّ معاملةً بـtry/catch
// وظنّ أن خرقَ القيد سيرميه — ظنّ خطأً، و`commit()` تمضي فوق خصمٍ لم يقع.
// **الفحصُ على المُرجَع لا على الاستثناء**، والإسقاطُ صريحٌ عنده.
foreach (array("UPDATE op_containers SET allocated_qty=allocated_qty WHERE id={$MAIN}",
               "UPDATE op_containers SET allocated_qty=500.00 WHERE id={$SUP}",
               "UPDATE op_containers SET allocated_qty=9999.00 WHERE id={$EQ}") as $q) {
    if (tryQ($conn, $q) !== '') { $okAll = false; break; }
}
if ($okAll) { $conn->commit(); } else { $conn->rollback(); }
check(!$okAll, 'المعاملةُ سقطت عند المستوى المتجاوز');
$after = $conn->query("SELECT allocated_qty FROM op_containers WHERE id={$SUP}")->fetch_assoc();
check((float) $after['allocated_qty'] == (float) $before['allocated_qty'],
    'و**الخصمُ المشروعُ قبله أُلغي معه**: ' . $before['allocated_qty'] . ' ← ' . $after['allocated_qty']
    . ' — أو لا تخصم شيئًا');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
