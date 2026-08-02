<?php
/**
 * UAT-0001 · بذرة ⑨ — تنقيةُ الأسماء: إزالةُ أسماء المالك وأسرته من بيانات
 * التجربة، وإحلالُ أسماءٍ واقعيةٍ محلَّ الأسماء المرقَّمة والتجريبية.
 *
 * قاعدةُ التمييز الحاكمة: **الأسماءُ المستوردةُ من المصنفات أسماءٌ حقيقيةٌ لأطرافٍ
 * حقيقيين ولا تُمَسّ** — و«حسن» و«أحمد» و«عبد الوهاب» شائعةٌ فيها، فلا تُستبدل
 * بالكلمة بل **بالسجل المحدَّد**. المستهدَفُ صراحةً هو عنقودُ «محمد سيد حسن غنيم»
 * وما بقي من بيانات العرض القديمة.
 */
require __DIR__ . '/_lib.php';

$db    = uat_db();
$CO    = UAT_COMPANY;
$n     = 0;

$set = function ($table, $col, $id, $val, $idCol = 'id') use ($db, &$n) {
    uat_guard($table);
    $st = $db->prepare("UPDATE `$table` SET `$col` = ? WHERE `$idCol` = ?");
    $st->bind_param('si', $val, $id);
    $st->execute();
    if ($st->affected_rows > 0) { $n++; uat_log($table . '.' . $col, 'استبدال'); }
};

// ── ① أسماءُ المالك وأسرته — بالسجل لا بالكلمة ───────────────────────────────
$FAMILY = [
    ['clients',   'client_name', 17,  'النذير خميس آدم للتعدين'],
    ['employees', 'name',        1,   'عثمان الطاهر إدريس'],
    ['employees', 'name',        2,   'الصادق مكي عبد الرحمن'],
    ['employees', 'name',        34,  'بابكر النور الأمين'],
    ['employees', 'name',        45,  'الطيب موسى دفع الله'],
    ['employees', 'name',        46,  'جمال الدين عوض السيد'],
    ['employees', 'name',        53,  'عمار الفاتح بشير'],
    ['employees', 'name',        821, 'منتصر الخير عبد الله'],
    ['users',     'name',        12,  'الطيب موسى دفع الله'],
    ['users',     'name',        56,  'عمار الفاتح بشير'],
    ['users',     'name',        78,  'عثمان الطاهر إدريس'],
    ['suppliers', 'contact_person_name', 10, 'هيثم عبد الرازق يوسف'],
];
foreach ($FAMILY as [$t, $c, $id, $v]) $set($t, $c, $id, $v);

foreach ([1, 6, 7, 8, 9] as $cid) $set('contracts', 'first_party', $cid, 'شركة إكوبيشن للاستثمار المحدودة');
$db->query("UPDATE employees SET emergency_contact_name = 'صلاح الدين قسم السيد' WHERE company_id=$CO AND emergency_contact_name LIKE '%محمد سيد%'");
$db->query("UPDATE rec_applications SET applicant_name = 'مصعب الطاهر النعيم' WHERE applicant_name LIKE '%محمد سيد%'");

// ── ② الأسماءُ المرقَّمةُ والتجريبية ─────────────────────────────────────────
$CLIENTS = [
    2   => 'شركة البركة للتعدين المحدودة',
    4   => 'شركة الرشيد للمقاولات المحدودة',
    363 => 'شركة وادي النيل للخدمات التعدينية',
    850 => 'شركة السافانا للتعدين المحدودة',
];
foreach ($CLIENTS as $id => $v) $set('clients', 'client_name', $id, $v);

$SUPPLIERS = [
    1    => 'شركة النسور لتأجير المعدات',
    2    => 'شركة الشمالية للحفريات',
    9    => 'ورشة دنقلا للمعدات الثقيلة',
    1598 => 'شركة البحر الأحمر للتوريدات',
    3    => 'شركة إكوبيشن للاستثمار المحدودة',
];
foreach ($SUPPLIERS as $id => $v) $set('suppliers', 'name', $id, $v);

// موردون ومشاريعُ مولَّدةٌ آليًّا (TSFAN · H15T · SQP) — أسماءٌ من واقع القطاع
$MINES = ['أبو حمد', 'الرهد', 'وادي العشار', 'جبل عامر', 'أم بادر', 'الطلحة', 'بئر الهيبة', 'الدويم', 'قبقبة', 'كتم',
          'السنقير', 'أبو دليق', 'الفاو', 'شمال كردفان', 'الرصيرص', 'دار مالي', 'أبو طليح', 'الجنيد', 'العطشان', 'بربر',
          'حلفا الجديدة', 'المتمة', 'شندي', 'عطبرة', 'دنقلا'];
$FIRMS = ['المهندس', 'الأصيل', 'الرواد', 'المستقبل', 'الصفوة', 'الأمانة', 'الفارس', 'النخبة', 'الوفاء', 'الجودة',
          'الإتقان', 'العمران', 'الثقة', 'الرافدين', 'السند', 'الميثاق', 'التقدم', 'الرؤية', 'المرتقى', 'الاعتماد'];

$i = 0;
foreach ($db->query("SELECT id,name FROM project WHERE company_id=$CO AND (name REGEXP 'TSFAN|H15T' OR name LIKE '%شهر 7%') ORDER BY id") as $p) {
    $set('project', 'name', (int) $p['id'], 'مشروع منجم ' . $MINES[$i % count($MINES)] . ' ' . (1 + intdiv($i, count($MINES))));
    $i++;
}
$i = 0;
foreach ($db->query("SELECT id,name FROM sites WHERE company_id=$CO AND (name REGEXP 'TSFAN|H15T' OR name LIKE '%شهر 7%') ORDER BY id") as $p) {
    $set('sites', 'name', (int) $p['id'], 'موقع منجم ' . $MINES[$i % count($MINES)] . ' ' . (1 + intdiv($i, count($MINES))));
    $i++;
}
$i = 0;
foreach ($db->query("SELECT id,name FROM suppliers WHERE company_id=$CO AND (name REGEXP 'TSFAN|SQP|^مورد' OR name LIKE '%محدث-2026%') ORDER BY id") as $s) {
    $set('suppliers', 'name', (int) $s['id'], 'شركة ' . $FIRMS[$i % count($FIRMS)] . ' للمعدات ' . (1 + intdiv($i, count($FIRMS))));
    $i++;
}

// حساباتُ الأدوار الموسومة «(تجريبي)» — تُسمّى بأسماءِ أشخاصٍ لتبدو كالواقع
$ROLES = [
    'المدير المالي' => 'الصديق عبد الماجد النور', 'محاسب الإدارة' => 'ياسر الأمين محمد خير',
    'مدير الإدارة المالية' => 'وليد الهادي عثمان', 'المراجع المالي' => 'أنس الفاتح إبراهيم',
    'أمين الخزينة' => 'مأمون عادل الطيب', 'قارئ مالي' => 'سيف الدين حامد بابكر',
];
foreach ($ROLES as $needle => $real) {
    foreach (['users' => 'name', 'employees' => 'name'] as $t => $c) {
        foreach ($db->query("SELECT id FROM `$t` WHERE `$c` LIKE '%" . $db->real_escape_string($needle) . "%(تجريبي)%'") as $r) {
            $set($t, $c, (int) $r['id'], $real);
        }
    }
}

// ── ③ تعميمُ التسمية على النسخ المشتقة ──────────────────────────────────────
// أسماءٌ منسوخةٌ في جداولَ أخرى — تُزامَن من مصدرها لا تُكتب يدويًّا
$db->query("UPDATE project p JOIN clients c ON c.id=p.client_id SET p.client = c.client_name WHERE p.company_id=$CO");
$db->query("UPDATE contracts ct JOIN project p ON p.id=ct.project_id JOIN clients c ON c.id=p.client_id
            SET ct.second_party = c.client_name WHERE ct.company_id=$CO");
$db->query("UPDATE settlements s JOIN suppliers sp ON sp.id=s.party_ref
            SET s.party_name = sp.name WHERE s.company_id=$CO AND s.party_type='supplier'");
$db->query("UPDATE contract_operational_sites cos JOIN sites st ON st.id=cos.site_id
            SET cos.scope_name = st.name WHERE cos.company_id=$CO");
$db->query("UPDATE equipment_ownership_registry r JOIN suppliers sp ON sp.name = r.actual_owner_name
            SET r.actual_owner_name = sp.name WHERE r.company_id=$CO");

// ── ④ فحصُ ما تبقّى ─────────────────────────────────────────────────────────
uat_print_report('البذرة ⑨ · تنقية الأسماء');
echo "   إجماليُّ السجلات المستبدَلة: $n\n";

$left = [];
foreach ([['clients', 'client_name'], ['employees', 'name'], ['users', 'name'], ['suppliers', 'name'],
          ['project', 'name'], ['sites', 'name'], ['contracts', 'second_party']] as [$t, $c]) {
    $r = $db->query("SELECT COUNT(*) n FROM `$t` WHERE `$c` LIKE '%محمد سيد%' OR `$c` LIKE '%غنيم%' OR `$c` LIKE '%شيماء%'
                     OR `$c` REGEXP '(عميل|مورد|مشروع|موظف)[[:space:]]*[0-9]+' OR `$c` LIKE '%تجريبي%' OR `$c` REGEXP 'TSFAN|H15T|SQP|LOOL'")->fetch_assoc()['n'];
    if ($r > 0) $left[] = "$t.$c=$r";
}
echo "   المتبقي بعد التنقية: " . ($left ? implode(' · ', $left) : 'صفر ✔') . "\n";
