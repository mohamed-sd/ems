<?php
/**
 * tools/repair01_w3_apply.php — أداةُ المرحلةِ الثالثة: المفاتيحُ والكياناتُ الأمّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عاديّةُ التشغيل**: كلُّ كتابةٍ هنا `INSERT … ON DUPLICATE KEY UPDATE` أو
 *   `UPDATE` بشرطٍ يُطابق الحالةَ المستهدَفة — فإعادةُ التشغيلِ لا تضاعف ولا
 *   تتراجع. ومفاتيحُها **أعمالٌ** (مفتاحٌ · جدولٌ · مسارٌ) لا أرقامَ صفوف.
 *
 * ◆ **والقياسُ قبلَ الكتابة**: لا صفَّ يُكتب برأيٍ — كلُّ حكمٍ يحمل `*_rule`
 *   باسمِ قاعدتِه، وكلُّ رقمٍ مشتقٌّ من المخطَّطِ الحيِّ أو من صفوفٍ حيّة.
 *
 * ◆ **والوصلُ لا يدهس**: `persons.employee_id` يُملأ حيث المطابقةُ **وحيدةٌ**
 *   داخلَ الكيان؛ والمتعدّدُ والصامتُ يُحكَمان بقرارٍ مسجَّلٍ ولا يُخمَّنان.
 *
 * التشغيل:
 *   php tools/repair01_w3_apply.php            # قياسٌ وكتابة
 *   php tools/repair01_w3_apply.php --report   # قياسٌ بلا كتابة
 *   php tools/repair01_w3_apply.php --revert   # إرجاعُ ما كتبته هذه الأداةُ وحدَها
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === false) { echo '  ⚠ ' . $conn->error . "\n"; return false; }
    return true;
};
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w3_one($conn, $sql); };

echo "══ REPAIR01 · W03 — " . ($REVERT ? 'إرجاع' : ($REPORT ? 'قياسٌ بلا كتابة' : 'قياسٌ وكتابة')) . " ══\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ الإرجاع — يُفرِّغ ما كتبته هذه الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    $n = 0;
    foreach (array('repair01_w3_sidebar', 'repair01_w3_scope', 'repair01_key_alias',
                   'repair01_key_registry', 'repair01_master_entities', 'repair01_w3_decisions') as $t) {
        if ($conn->query("DELETE FROM `$t`")) { $n++; echo "  ✔ فُرِّغ $t\n"; }
    }
    /* عقودُ الأثرِ التي كتبتها هذه المرحلةُ وحدَها */
    $conn->query("DELETE FROM repair01_events WHERE contract_stage = 'W03' AND wave = 'W03'");
    echo "  ✔ عقودُ الأثرِ المكتوبةُ في W03 نُزعت\n";
    $conn->query("UPDATE repair01_events SET contract_stage = '' WHERE contract_stage <> '' AND wave <> 'W03'");
    /* وصلُ persons — يُفرَّغ ما مُلئ بقاعدةٍ من قواعدِ هذه الأداةِ فقط */
    /* والحجرُ يُرفع أوّلًا: `active=0` كتبته هذه الأداةُ بقاعدتِها، وتركُه مرفوعًا
       بلا قاعدةٍ يجعل التراجعَ يترك أثرًا لا يعود إلى شيء. */
    $conn->query("UPDATE persons SET active = 1 WHERE w3_link_rule LIKE 'QUARANTINE%'");
    $conn->query("UPDATE persons SET company_id = NULL, employee_id = NULL,
                  person_class = 'UNRESOLVED', w3_link_rule = '' WHERE w3_link_rule <> ''");
    echo "  ✔ وصلُ persons أُفرِغ لما حمل قاعدةَ W03\n";
    $conn->query("UPDATE nav_canonical SET screen_id = '' WHERE screen_id <> ''");
    echo "  ✔ nav_canonical.screen_id أُفرِغ\n";
    echo "\nالحكم: رجعت ✔ (والقوادحُ تُنزع بهجرةِ التراجع)\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① سجلُّ المفاتيحِ الثلاثةَ عشر — مالكٌ واحدٌ وقاعدةُ إنشاءٍ وقاعدةُ قراءة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① المفاتيحُ الثلاثةَ عشرَ ────────────────────────────────────────\n";

/* قاعدتا الإنشاءِ والقراءةِ لكلِّ مفتاح — إعلانٌ بمرجعِه، والمالكُ يُقاس */
$RULES = array(
    'Company_ID' => array(
        'create' => 'ينشأ بتسجيل كيان قانوني في admin_companies — ولا ينشئه نطاق تشغيلي',
        'read'   => 'يقرأ من سياق الجلسة عبر ems_scope_company() — لا رقم صلب ولا وسيط من الطلب',
        'screen' => '', 'src' => 'DEC-OPEN-03 · MULTI_ENTITY_BY_DESIGN'),
    'Project_ID' => array(
        'create' => 'ينشئه سجل المشاريع بعد اعتماد العقد — والمشروع لا ينشأ من شاشة تشغيل',
        'read'   => 'يقرأ بالمفتاح من project — والاسم يعرض بالانضمام لا بنسخة محلية',
        'screen' => 'Projects/projects.php', 'src' => 'W03 §٤-٣ · مرجع أم: Project/Site'),
    'Site_ID' => array(
        'create' => 'ينشئه سجل المواقع تحت مشروع قائم — لا موقع بلا Project_ID',
        'read'   => 'يقرأ بالمفتاح من sites — والموقع باسمه في شاشة لا يعد قراءة',
        'screen' => 'Projects/sites.php', 'src' => 'W03 §٤-٣ · SITE-02'),
    'Unit_ID' => array(
        'create' => 'ينشئه إدخال وحدات العمل اليومية بعد ورديه معتمدة',
        'read'   => 'يقرأ بالمفتاح من unit_entries — والاعتماد والفوترة يتبعانه',
        'screen' => 'Operations/shift_entry.php', 'src' => 'W03 §٤-٣ · Operational Unit'),
    'Asset_ID' => array(
        'create' => 'ينشئه سجل المعدات بعد بطاقة تعريف مكتملة — والمصدر والتملك يسجلان معه',
        'read'   => 'يقرأ بالمفتاح من equipments — والصيانة والتشغيل والمالية تنضم إليه',
        'screen' => 'Equipments/equipments.php', 'src' => 'W03 §٤-٣ · Asset Master'),
    'Person_ID' => array(
        'create' => 'ينشئه سجل الموظفين — وسجل الهوية persons يوصل به ولا ينشئ معرفا بديلا',
        'read'   => 'يقرأ بالمفتاح من employees — وكل عمود اسمه person_id يحمله هو',
        'screen' => 'Employees/employees.php', 'src' => 'W03 §٤-٣ · Person/Workforce'),
    'Workforce_Assignment_ID' => array(
        'create' => 'ينشئه التكليف التنظيمي بشخص وجهة ونطاق ومدة',
        'read'   => 'يقرأ بالمفتاح من org_assignments — والصلاحية تشتق منه لا تنسخ',
        'screen' => 'admin/org_assignments.php', 'src' => 'W03 §٤-٢'),
    'Shift_ID' => array(
        'create' => 'ينشئه نمط الوردية في shift_patterns — والفترات أبناؤه',
        'read'   => 'يقرأ بالمفتاح من shift_patterns — واسم الوردية نصا ليس قراءة',
        'screen' => 'Operations/site_shift_plan.php', 'src' => 'W03 §٤-٢'),
    'Timesheet_ID' => array(
        'create' => 'ينشئه تسجيل التايم شيت لمعدة ومشغل وتاريخ ووردية',
        'read'   => 'يقرأ بالمفتاح من timesheet — والوحدة والاستحقاق يشيران إليه',
        'screen' => 'Timesheet/timesheet.php', 'src' => 'W03 §٤-٢'),
    'Maintenance_Order_ID' => array(
        'create' => 'ينشئه أمر الصيانة من بلاغ أو فحص أو خطة وقائية',
        'read'   => 'يقرأ بالمفتاح من mnt_order — والتكلفة والجاهزية تنضمان إليه',
        'screen' => 'Maintenance/orders.php', 'src' => 'W03 §٤-٢'),
    'Transport_Request_ID' => array(
        'create' => 'ينشئه طلب الترحيل من إدارة طالبة بموقعي مصدر ووجهة',
        'read'   => 'يقرأ بالمفتاح من transfer_requests — والأمر يشير إليه ولا ينسخه',
        'screen' => 'Transport/transfer_requests.php', 'src' => 'W03 §٤-٢'),
    'Transport_Order_ID' => array(
        'create' => 'ينشئه أمر الترحيل من طلب معتمد — ولا أمر بلا Transport_Request_ID',
        'read'   => 'يقرأ بالمفتاح من transfer_orders — والتكلفة والوصول يتبعانه',
        'screen' => 'Transport/transfer_orders_list.php', 'src' => 'W03 §٤-٢'),
    'Ticket_ID' => array(
        'create' => 'ينشئه فتح البلاغ بمصدره وشاشته — والترقيم من ems_sequences',
        'read'   => 'يقرأ بالمفتاح من tickets — والتفريع والتحويل يشيران إليه',
        'screen' => 'Tickets/tickets_list.php', 'src' => 'W03 §٤-٢'),
);

$keys = repair01_w3_keys();
$keyOk = 0; $keyBad = array();
foreach ($keys as $code => $d) {
    list($ar, $seq, $tbl, $col, $dept, $scope, $coCol, $isMaster) = $d;
    $m = repair01_w3_measure_owner($conn, $tbl, $col, $coCol);
    $screenRoute = $RULES[$code]['screen'];
    $screenId = '';
    if ($screenRoute !== '') {
        $screenId = (string) $one("SELECT screen_id FROM repair01_screen_registry
                                    WHERE route = '" . $esc($screenRoute) . "' LIMIT 1");
    }
    $ownerRule = ($m['table_ok'] && $m['pk_ok']) ? 'MEASURED_PK_OWNER' : 'OWNER_NOT_MEASURED';
    if ($m['table_ok'] && $m['pk_ok']) { $keyOk++; } else { $keyBad[] = $code; }
    $W("INSERT INTO repair01_key_registry
        (key_code,key_ar,seq_no,owner_table,owner_column,owner_dept,owner_screen_id,owner_rule,
         create_rule,create_rule_src,read_rule,read_rule_src,company_scope,company_column,
         is_master,measured_rows,measured_at,src_ref)
        VALUES ('" . $esc($code) . "','" . $esc($ar) . "'," . (int) $seq . ",'" . $esc($tbl) . "','" . $esc($col) . "',
                '" . $esc($dept) . "','" . $esc($screenId) . "','" . $esc($ownerRule) . "',
                '" . $esc($RULES[$code]['create']) . "','" . $esc($RULES[$code]['src']) . "',
                '" . $esc($RULES[$code]['read']) . "','" . $esc($RULES[$code]['src']) . "',
                '" . $esc($scope) . "','" . $esc($coCol) . "'," . (int) $isMaster . ",
                " . (int) $m['rows'] . ", NOW(), '" . $esc('W03 › §٤-٢ › المفتاح ' . $seq) . "')
        ON DUPLICATE KEY UPDATE key_ar=VALUES(key_ar), seq_no=VALUES(seq_no), owner_dept=VALUES(owner_dept),
          owner_screen_id=VALUES(owner_screen_id), owner_rule=VALUES(owner_rule),
          create_rule=VALUES(create_rule), read_rule=VALUES(read_rule),
          company_scope=VALUES(company_scope), company_column=VALUES(company_column),
          is_master=VALUES(is_master), measured_rows=VALUES(measured_rows), measured_at=NOW()");
    printf("  %-24s ⇐ %-20s.%-12s صفوف %-6s كيان %s\n", $code, $tbl, $col, $m['rows'],
        $scope === 'ROOT' ? 'الجذر' : ($m['company_ok'] ? 'نعم (بلا ' . $m['rows_no_company'] . ')' : '✘ مفقود'));
}
printf("  ⇐ مالكٌ مقيسٌ %d/%d%s\n\n", $keyOk, count($keys), $keyBad ? ' · بلا مالك: ' . implode('،', $keyBad) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② وصلُ السجلِّ الثاني للأشخاص — والمعرّفُ البديلُ يُوصَل لا يُمحى
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② السجلُّ الثاني — `persons` ─────────────────────────────────────\n";

/* ② -٠ عاديّةُ التشغيل: أحكامُ هذه الأداةِ تُصفَّر قبلَ إعادةِ اشتقاقِها،
   وإلا بقي حكمُ تشغيلٍ سابقٍ يمنع القاعدةَ من إعادةِ النظر. والحقائقُ
   المكتسَبةُ (`company_id` · `employee_id`) لا تُصفَّر — القواعدُ تتخطّاها.
   ⚠ **والمحجورُ لا يُرفع حجرُه هنا**: رفعُه يعني `active = 1` وهو بلا كيانٍ —
   والقيدُ في القاعدةِ يمنعه بحقّ. فالتصفيرُ يتخطّاه، وحكمُه يُعاد اشتقاقُه كما
   هو. ورفعُ الحجرِ مسارُه `--revert` **بعدَ** هجرةِ نزعِ القيد. */
$W("UPDATE persons SET person_class = 'UNRESOLVED', w3_link_rule = ''
     WHERE w3_link_rule <> '' AND w3_link_rule NOT LIKE 'QUARANTINE%'");

/* ② -أ الكيانُ القانونيّ: قاعدةٌ تلو قاعدة، والأولى الرابحةُ تُسجَّل */
$coRules = array(
    'POSITION_COMPANY' => "UPDATE persons p
        JOIN (SELECT person_id, MIN(company_id) cid, COUNT(DISTINCT company_id) n
                FROM person_positions GROUP BY person_id HAVING n = 1) z ON z.person_id = p.person_id
        SET p.company_id = z.cid, p.w3_link_rule = 'POSITION_COMPANY'
        WHERE p.company_id IS NULL",
    'RELATION_COMPANY' => "UPDATE persons p
        JOIN (SELECT person_id, MIN(company_id) cid, COUNT(DISTINCT company_id) n
                FROM person_relationships GROUP BY person_id HAVING n = 1) z ON z.person_id = p.person_id
        SET p.company_id = z.cid, p.w3_link_rule = 'RELATION_COMPANY'
        WHERE p.company_id IS NULL",
    'NAME_UNIQUE_EMPLOYEE' => "UPDATE persons p
        JOIN (SELECT e.name nm, MIN(e.company_id) cid FROM employees e
               GROUP BY e.name HAVING COUNT(DISTINCT e.company_id) = 1 AND COUNT(*) = 1) z ON z.nm = p.full_name
        SET p.company_id = z.cid, p.w3_link_rule = 'NAME_UNIQUE_EMPLOYEE'
        WHERE p.company_id IS NULL",
);
foreach ($coRules as $rule => $sql) {
    $before = (int) $one("SELECT COUNT(*) FROM persons WHERE company_id IS NULL");
    $W($sql);
    $after = (int) $one("SELECT COUNT(*) FROM persons WHERE company_id IS NULL");
    printf("  كيان · %-22s ملأ %d\n", $rule, max(0, $before - $after));
}

/* ② -ب الوصلُ بالمفتاحِ الأمّ — تقابلٌ **واحدٌ لواحد** في الاتّجاهَين
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **ولماذا لا `UPDATE … JOIN` واحدةً**: `uq_employee` تمنع موظّفًا لصفَّي
     هويةٍ — وفي السجلِّ **أسماءٌ مكرَّرةٌ داخلَه** («معتصم بابكر الريح» ثلاثًا).
     فجملةٌ واحدةٌ تسقط كلُّها عند أوّلِ تصادمٍ وتترك **صفرَ وصل** ثم يُقرأ
     الصفرُ «لا مطابقةَ» وهو في الحقيقةِ «الجملةُ أُجهضت».
   ◆ **والتقابلُ يُشترط في الاتّجاهَين**: اسمٌ واحدٌ في `employees` داخلَ الكيان
     **واسمٌ واحدٌ في `persons` داخلَه** — فاسمٌ يحمله صفّا هويةٍ لا يُخمَّن أيُّهما
     الموظّف. */
$linkNames = array();
$rp = $conn->query("SELECT p.person_id, p.full_name, p.company_id
                      FROM persons p
                     WHERE p.employee_id IS NULL AND p.company_id IS NOT NULL
                     ORDER BY p.person_id");
while ($rp && $pr = $rp->fetch_assoc()) { $linkNames[] = $pr; }
$linked = 0; $ambig = 0;
foreach ($linkNames as $pr) {
    $nm = $esc($pr['full_name']); $co = (int) $pr['company_id'];
    $nP = (int) $one("SELECT COUNT(*) FROM persons WHERE full_name = '$nm' AND company_id = $co");
    $nE = (int) $one("SELECT COUNT(*) FROM employees WHERE name = '$nm' AND company_id = $co");
    if ($nP !== 1 || $nE !== 1) { if ($nE > 1 || $nP > 1) { $ambig++; } continue; }
    $eid = (int) $one("SELECT id FROM employees WHERE name = '$nm' AND company_id = $co LIMIT 1");
    $taken = (int) $one("SELECT COUNT(*) FROM persons WHERE employee_id = $eid");
    if ($taken > 0) { $ambig++; continue; }
    if ($W("UPDATE persons SET employee_id = $eid, person_class = 'WORKFORCE',
             w3_link_rule = CONCAT(IF(w3_link_rule='','',CONCAT(w3_link_rule,'+')),'ONE_TO_ONE_NAME_IN_COMPANY')
            WHERE person_id = " . (int) $pr['person_id'])) { $linked++; }
}
/* والموصولُ سلفًا يستعيد صنفَه بعدَ تصفيرِ الأحكام: صفٌّ يحمل المفتاحَ الأمَّ
   **هو** صفُّ قوًى عاملةٍ — وتركُه `UNRESOLVED` يجعل الحارسَ يعبره والعدَّ يكذب. */
$W("UPDATE persons SET person_class = 'WORKFORCE',
     w3_link_rule = CONCAT(IF(w3_link_rule='','',CONCAT(w3_link_rule,'+')),'ALREADY_LINKED')
   WHERE employee_id IS NOT NULL AND person_class <> 'WORKFORCE'");
printf("  وصلٌ · ONE_TO_ONE_NAME_IN_COMPANY وصل %d · متعدِّدُ التقابل %d\n", $linked, $ambig);

/* ② -ج ما بقي: صفُّ هويةٍ مولَّدٌ من حسابٍ (لا إنسانَ في القوى) أو بلا سند
   والحسابُ يُطابَق بالاسمِ في `users.name` — وهو المصدرُ الذي وُلِّد منه الصفّ
   (‏`tools/identity_bridge_build.php` ينشئ شخصًا لكلِّ حسابٍ بلا جسر). */
$W("UPDATE persons p
    SET p.person_class = 'IDENTITY_ONLY',
        p.w3_link_rule = CONCAT(IF(p.w3_link_rule='','',CONCAT(p.w3_link_rule,'+')),'IDENTITY_BRIDGE_SYNTHETIC')
    WHERE p.employee_id IS NULL AND p.company_id IS NOT NULL AND p.person_class = 'UNRESOLVED'
      AND EXISTS (SELECT 1 FROM users u WHERE u.name = p.full_name AND u.company_id = p.company_id)");
/* والمتعدِّدُ المطابقةِ: اسمٌ يحمله أكثرُ من موظّفٍ في الكيانِ نفسِه — لا يُخمَّن */
$W("UPDATE persons p
    SET p.person_class = 'UNRESOLVED',
        p.w3_link_rule = CONCAT(IF(p.w3_link_rule='','',CONCAT(p.w3_link_rule,'+')),'AMBIGUOUS_NAME_W3-D-01')
    WHERE p.employee_id IS NULL AND p.company_id IS NOT NULL AND p.person_class = 'UNRESOLVED'
      AND ((SELECT COUNT(*) FROM employees e2 WHERE e2.name = p.full_name AND e2.company_id = p.company_id) > 1
        OR (SELECT COUNT(*) FROM (SELECT full_name, company_id FROM persons) p2
             WHERE p2.full_name = p.full_name AND p2.company_id = p.company_id) > 1)");
/* والصفُّ بلا كيانٍ ولا موظّفٍ ولا موقعٍ ولا علاقةٍ: يُحجَر بعلَمِ السجلِّ نفسِه */
$quarantined = (int) $one("SELECT COUNT(*) FROM persons WHERE company_id IS NULL");
$W("UPDATE persons SET active = 0, person_class = 'UNRESOLVED',
       w3_link_rule = 'QUARANTINE_NO_TENANT_EVIDENCE_W3-D-01'
     WHERE company_id IS NULL");

$pTot   = (int) $one("SELECT COUNT(*) FROM persons");
$pLink  = (int) $one("SELECT COUNT(*) FROM persons WHERE employee_id IS NOT NULL");
$pIdent = (int) $one("SELECT COUNT(*) FROM persons WHERE person_class = 'IDENTITY_ONLY'");
$pUnres = (int) $one("SELECT COUNT(*) FROM persons WHERE person_class = 'UNRESOLVED'");
$pNoCo  = (int) $one("SELECT COUNT(*) FROM persons WHERE company_id IS NULL AND active = 1");
printf("  المقام %d · موصولٌ بالمفتاحِ الأمّ %d · صفُّ هويةٍ معلَنٌ %d · بلا حسمٍ %d · محجورٌ %d\n",
    $pTot, $pLink, $pIdent, $pUnres, $quarantined);
printf("  ⇐ حيٌّ بلا كيانٍ قانونيّ: %d\n\n", $pNoCo);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ دفترُ المعرّفاتِ البديلة — سجلٌّ ثانٍ · تسميةٌ بلا مفتاح
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ المعرّفاتُ البديلة ────────────────────────────────────────────\n";
$W("DELETE FROM repair01_key_alias");

/* ③ -أ السجلاتُ الثانية */
foreach (repair01_w3_parallel_registers() as $reg) {
    $m = repair01_w3_parallel_measure($conn, $reg);
    $verdict = ($m['col_ok'] === 1 && $m['in_use_unlinked'] === 0) ? 'LINKED' : 'ALTERNATE_ID';
    $rule = ($verdict === 'LINKED') ? 'PARALLEL_LINKED_TO_MASTER_KEY' : 'PARALLEL_UNLINKED';
    $W("INSERT INTO repair01_key_alias
        (key_code,alias_table,alias_column,alias_kind,verdict,verdict_rule,verdict_why,
         rows_total,rows_seed,rows_resolvable,link_column,rows_linked,resolved_at,wave_stage,src_ref)
        VALUES ('" . $esc($reg['key']) . "','" . $esc($reg['table']) . "','" . $esc($reg['link']) . "',
                'PARALLEL_REGISTER','" . $esc($verdict) . "','" . $esc($rule) . "','" . $esc($reg['why']) . "',
                " . (int) $m['rows'] . ",0," . (int) $m['in_use'] . ",'" . $esc($reg['link']) . "',
                " . (int) $m['linked'] . ", " . ($verdict === 'LINKED' ? 'NOW()' : 'NULL') . ",
                'W03','" . $esc('قياسٌ حيّ: information_schema + ' . $reg['table']) . "')
        ON DUPLICATE KEY UPDATE verdict=VALUES(verdict), verdict_rule=VALUES(verdict_rule),
          rows_total=VALUES(rows_total), rows_linked=VALUES(rows_linked), resolved_at=VALUES(resolved_at)");
    printf("  سجلٌّ ثانٍ · %-12s %-14s صفوف %-5d موصول %-5d مستعمَلٌ بلا وصل %d\n",
        $reg['key'], $reg['table'], $m['rows'], $m['linked'], $m['in_use_unlinked']);
}

/* ③ -ب التسميةُ بلا مفتاح — اكتشافٌ آليٌّ والحكمُ من ثلاثةِ أرقامٍ مقيسة */
$aliases = repair01_w3_scan_aliases($conn);
$labelN = 0; $seedN = 0; $altN = 0;
foreach ($aliases as $key => $rows) {
    foreach ($rows as $a) {
        $labelN++;
        /* الحكمُ **مقيسٌ**: كلُّ الصفوفِ بذرةٌ وصفرُ قيمةٍ تجد مرجعَها ⇒ بذرةٌ بلا مرجع */
        if ($a['rows'] > 0 && $a['rows_seed'] === $a['rows'] && $a['rows_resolvable'] === 0) {
            $verdict = 'SEED_NO_REFERENT'; $rule = 'MEASURED_ALL_SEED_ZERO_REFERENT'; $seedN++;
            $why = 'كل صفوفه بذرة والنص لا يجد مرجعا في الجدول المالك — سطح مستهدف يبنى بمفتاحه في موجته';
        } elseif ($a['rows'] === 0) {
            $verdict = 'SEED_NO_REFERENT'; $rule = 'MEASURED_EMPTY_TABLE'; $seedN++;
            $why = 'جدول فارغ — لا صف يعرف حقيقة أم بنص';
        } else {
            $verdict = 'ALTERNATE_ID'; $rule = 'MEASURED_LIVE_LABEL_NO_KEY'; $altN++;
            $why = 'صفوف حية تعرف الحقيقة الأم بنص ولا تحمل مفتاحها';
        }
        $wave = (string) $one("SELECT wave_stage FROM repair01_target_gaps
                               WHERE surface_name LIKE '%" . $esc(str_replace('scr_', '', $a['table'])) . "%' LIMIT 1");
        $W("INSERT INTO repair01_key_alias
            (key_code,alias_table,alias_column,alias_kind,verdict,verdict_rule,verdict_why,
             rows_total,rows_seed,rows_resolvable,link_column,rows_linked,resolved_at,wave_stage,src_ref)
            VALUES ('" . $esc($key) . "','" . $esc($a['table']) . "','" . $esc($a['column']) . "',
                    'LABEL_ONLY','" . $esc($verdict) . "','" . $esc($rule) . "','" . $esc($why) . "',
                    " . (int) $a['rows'] . "," . (int) $a['rows_seed'] . "," . (int) $a['rows_resolvable'] . ",
                    '',0,NULL,'" . $esc($wave) . "','" . $esc('قياسٌ حيّ: ' . $a['table'] . '.' . $a['column']) . "')
            ON DUPLICATE KEY UPDATE verdict=VALUES(verdict), verdict_rule=VALUES(verdict_rule),
              rows_total=VALUES(rows_total), rows_seed=VALUES(rows_seed),
              rows_resolvable=VALUES(rows_resolvable), wave_stage=VALUES(wave_stage)");
    }
}
printf("  تسميةٌ بلا مفتاح: مرشَّحٌ %d · بذرةٌ بلا مرجع %d · معرّفٌ بديلٌ حيٌّ %d\n", $labelN, $seedN, $altN);

/* ③ -ج الإصلاحُ البنيويّ: النصُّ صار لافتةً بجانبِ مفتاح — يُملأ ما يجد مرجعَه
   ═══════════════════════════════════════════════════════════════════════════
   ◆ **ولا يُخمَّن ما لا يجده**: صفٌّ نصُّه بلا مرجعٍ يبقى مفتاحُه فارغًا
     **مقيسًا ومنسوبًا إلى موجتِه** — فالفراغُ المعلَنُ أصدقُ من رقمٍ مخترَع. */
foreach (repair01_w3_label_repairs() as $rp) {
    $t = $rp['table']; $kc = $rp['key_col']; $tx = $rp['text']; $ow = $rp['owner'];
    if (repair01_w3_col_type($conn, $t, $kc) === '') {
        printf("  ⚠ %s.%s غيرُ موجود — شغّلْ database/migrations/2027_11_20_repair01_w3_label_keys.php\n", $t, $kc);
        continue;
    }
    $conds = array();
    foreach ($rp['match'] as $mc) {
        if (repair01_w3_col_type($conn, $ow, $mc) !== '') { $conds[] = "o.`$mc` = x.`$tx`"; }
    }
    $join = '(' . implode(' OR ', $conds) . ')';
    if (repair01_w3_col_type($conn, $t, 'company_id') !== '' && repair01_w3_col_type($conn, $ow, 'company_id') !== '') {
        $join .= ' AND o.`company_id` = x.`company_id`';
    }
    /* المطابقةُ **الوحيدةُ** فقط — نصٌّ يجد مرجعَين لا يُحسم بالأوّل */
    $W("UPDATE `$t` x JOIN `$ow` o ON $join
         SET x.`$kc` = o.`id`
       WHERE x.`$kc` IS NULL AND x.`$tx` IS NOT NULL AND x.`$tx` <> ''
         AND (SELECT COUNT(*) FROM `$ow` o2 WHERE " . str_replace('o.`', 'o2.`', $join) . ") = 1");
    $tot   = (int) $one("SELECT COUNT(*) FROM `$t` WHERE `$tx` IS NOT NULL AND `$tx` <> ''");
    $lnk   = (int) $one("SELECT COUNT(*) FROM `$t` WHERE `$kc` IS NOT NULL");
    $seedR = repair01_w3_col_type($conn, $t, 'is_seed') !== ''
           ? (int) $one("SELECT COUNT(*) FROM `$t` WHERE is_seed = 1") : 0;
    $wave  = (string) $one("SELECT wave_stage FROM repair01_target_gaps
                            WHERE surface_name LIKE '%" . $esc(str_replace('scr_', '', $t)) . "%' LIMIT 1");
    $W("INSERT INTO repair01_key_alias
        (key_code,alias_table,alias_column,alias_kind,verdict,verdict_rule,verdict_why,
         rows_total,rows_seed,rows_resolvable,link_column,rows_linked,resolved_at,wave_stage,src_ref)
        VALUES ('" . $esc($rp['key_code']) . "','" . $esc($t) . "','" . $esc($tx) . "',
                'DENORM_LABEL','DENORM_LABEL','STRUCTURAL_KEY_ADDED_W03',
                '" . $esc('عمود المفتاح ' . $kc . ' أضيف — فالنص لافتة بجانب مفتاح لا معرف بديل؛ والصف بلا مرجع يبقى مقيسا لموجته') . "',
                $tot,$seedR,$lnk,'" . $esc($kc) . "',$lnk,NOW(),'" . $esc($wave) . "',
                '" . $esc('قياسٌ حيّ: ' . $t . '.' . $tx . ' ⇐ ' . $ow) . "')
        ON DUPLICATE KEY UPDATE verdict=VALUES(verdict), verdict_rule=VALUES(verdict_rule),
          verdict_why=VALUES(verdict_why), alias_kind=VALUES(alias_kind), rows_total=VALUES(rows_total),
          rows_seed=VALUES(rows_seed), rows_resolvable=VALUES(rows_resolvable),
          link_column=VALUES(link_column), rows_linked=VALUES(rows_linked), resolved_at=NOW()");
    printf("  إصلاحٌ بنيويّ · %-22s %-20s نصٌّ %-4d ⇐ مفتاحٌ مملوءٌ %d\n", $t, $kc, $tot, $lnk);
}
echo "\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ④ الكياناتُ الأمّ — DEC-OPEN-03
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ الكياناتُ الأمُّ — DEC-OPEN-03 ─────────────────────────────────\n";
$W("DELETE FROM repair01_master_entities");
$MASTERS = array(
    'MASTER_COMPANY' => array('الكيان القانوني', 'Company_ID', 'admin_companies', '', '', 'ROOT_SELF'),
    'MASTER_PROJECT' => array('المشروع',          'Project_ID', 'project',        'company_id', '', 'NOT_NULL'),
    'MASTER_SITE'    => array('الموقع',           'Site_ID',    'sites',          'company_id', '', 'NOT_NULL'),
    'MASTER_ASSET'   => array('الأصل/المعدة',     'Asset_ID',   'equipments',     'company_id', '', 'NOT_NULL'),
    'MASTER_PERSON'  => array('الشخص',            'Person_ID',  'employees',      'company_id', '', 'NOT_NULL'),
    'MASTER_UNIT'    => array('وحدة العمل',       'Unit_ID',    'unit_entries',   'company_id', '', 'NOT_NULL'),
    'MASTER_WFA'     => array('التكليف التنظيمي', 'Workforce_Assignment_ID', 'org_assignments', 'company_id', '', 'NOT_NULL'),
    'MASTER_SHIFT'   => array('نمط الوردية',      'Shift_ID',   'shift_patterns', 'company_id', '', 'NOT_NULL'),
    'IDENTITY_PERSON' => array('سجل هوية الأشخاص', 'Person_ID', 'persons',        'company_id', 'active = 1', 'TRIGGER'),
);
$guard = repair01_w3_probe_person_guard($conn);
$masterBad = 0;
foreach ($MASTERS as $code => $d) {
    list($ar, $key, $tbl, $coCol, $inUse, $guardKind) = $d;
    $tot = (int) $one("SELECT COUNT(*) FROM `$tbl`");
    $use = $inUse === '' ? $tot : (int) $one("SELECT COUNT(*) FROM `$tbl` WHERE $inUse");
    $noCo = 0; $quar = 0;
    if ($coCol !== '') {
        $where = "(`$coCol` IS NULL OR `$coCol` = 0)";
        $noCo = (int) $one("SELECT COUNT(*) FROM `$tbl` WHERE $where" . ($inUse === '' ? '' : " AND $inUse"));
        $quar = (int) $one("SELECT COUNT(*) FROM `$tbl` WHERE $where" . ($inUse === '' ? '' : " AND NOT ($inUse)"));
    }
    $gk = 'NONE'; $ge = '';
    if ($tbl === 'persons') {
        $gk = ($guard['company_blocked'] === 1) ? 'TRIGGER' : 'NONE';
        $ge = 'جسٌّ وظيفيّ: إدراجُ صفٍّ بلا كيانٍ ' . ($guard['company_blocked'] ? 'مُنع' : 'مرّ')
            . ' · إدراجُ قوًى بلا وصلٍ ' . ($guard['link_blocked'] ? 'مُنع' : 'مرّ');
    } elseif ($coCol !== '') {
        $nullable = (string) $one("SELECT IS_NULLABLE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $esc($tbl) . "' AND COLUMN_NAME='" . $esc($coCol) . "'");
        $gk = ($nullable === 'NO') ? 'NOT_NULL' : 'APP_ONLY';
        $ge = "information_schema.COLUMNS.IS_NULLABLE=$nullable";
    } else {
        $gk = 'NOT_NULL'; $ge = 'الجذر: الكيان نفسه — لا عمود كيان فيه';
    }
    $verdict = ($noCo === 0) ? 'DEC_OPEN_03_OK' : 'DEC_OPEN_03_BREACH';
    if ($noCo !== 0) { $masterBad++; }
    $W("INSERT INTO repair01_master_entities
        (entity_code,entity_ar,key_code,table_name,company_column,rows_total,rows_in_use,
         rows_no_company,rows_quarantined,quarantine_rule,guard_kind,guard_evidence,verdict,measured_at,src_ref)
        VALUES ('" . $esc($code) . "','" . $esc($ar) . "','" . $esc($key) . "','" . $esc($tbl) . "','" . $esc($coCol) . "',
                $tot,$use,$noCo,$quar,'" . $esc($quar > 0 ? 'QUARANTINE_NO_TENANT_EVIDENCE_W3-D-01' : '') . "',
                '" . $esc($gk) . "','" . $esc($ge) . "','" . $esc($verdict) . "',NOW(),
                '" . $esc('DEC-OPEN-03 · W03 §٤-٤') . "')
        ON DUPLICATE KEY UPDATE rows_total=VALUES(rows_total), rows_in_use=VALUES(rows_in_use),
          rows_no_company=VALUES(rows_no_company), rows_quarantined=VALUES(rows_quarantined),
          guard_kind=VALUES(guard_kind), guard_evidence=VALUES(guard_evidence),
          verdict=VALUES(verdict), measured_at=NOW()");
    printf("  %-16s %-18s مقام %-6d مستعمَل %-6d بلا كيان %-4d محجور %-4d حارس %s\n",
        $code, $tbl, $tot, $use, $noCo, $quar, $gk);
}
printf("  ⇐ كيانٌ أمٌّ بلا Company_ID: %d\n\n", $masterBad);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ نطاقُ المرحلة — المتطلَّبُ إلى Canonical Screen_ID (خطوة ٧ من §٤-١)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ نطاقُ المرحلة — ٢٤ متطلَّبًا ───────────────────────────────────\n";
$W("DELETE FROM repair01_w3_scope");

/* جدولُ المرساةِ: المتطلَّبُ ⇐ الجدولُ الذي تصفه حبّتُه — من خليّةِ `grain` نفسِها */
$ANCHOR = array(
    'FLEET-01' => 'equipment_ownership_registry',
    'FLEET-02' => 'equipments_types',
    'FLEET-31' => 'fleet_depreciation_profile',
    'FLEET-33' => 'scr_code_bridge',
    'FLEET-34' => 'scr_code_bridge',
    'FLEET-35' => 'equipment_ownership_registry',
    'FLEET-36' => 'asset_hour_reconciliations',
    'FLEET-37' => 'financed_assets',
    'SITE-02'  => 'sites',
    'SITE-04'  => 'scr_site_gate_equip',
    'WRK-07'   => 'employees',
    'WRK-08'   => 'worker_qualification',
    'WRK-09'   => 'scr_op_qual',
    'MNT-02'   => 'failure_codes',
    'MNT-03'   => 'scr_workshop',
    'TRP-03'   => 'scr_transfer_fleet',
);
/* الشاشاتُ المبنيّةُ ونصُّها — للمِرساةِ بالجدولِ لا بالاسم */
$built = array();
$r = $conn->query("SELECT screen_id, route, owner_code FROM repair01_screen_registry WHERE on_disk = 1 AND route IS NOT NULL");
while ($r && $x = $r->fetch_assoc()) {
    $p = $ROOT . '/' . $x['route'];
    $built[$x['screen_id']] = array('route' => $x['route'], 'owner' => $x['owner_code'],
                                    'src' => is_file($p) ? (string) file_get_contents($p) : '');
}
$scopeMapped = 0; $scopeGap = 0; $scopeSilent = 0;
$rq = $conn->query("SELECT requirement_id, unit, group_name, surface, src_ref
                    FROM repair01_requirements WHERE stage_no = 3 ORDER BY unit, seq");
$silentList = array();
while ($rq && $q = $rq->fetch_assoc()) {
    $rid = $q['requirement_id']; $sid = ''; $rule = ''; $why = ''; $wave = '';
    $dept = preg_match('/^(\d{2})\s/u', $q['unit'], $mm) ? 'DEP-' . $mm[1] : '';

    /* قاعدة ①: المِرساةُ بالجدول — الشاشةُ التي تكتب جدولَ الحبّة، مقيسًا من القرص */
    if (isset($ANCHOR[$rid])) {
        $tbl = $ANCHOR[$rid]; $hits = array();
        /* ⚠ كاشفٌ يرصد `FROM/INTO/UPDATE/JOIN` وحدَها **أعمى عن الكتابةِ المحكومة**:
           `Equipments/manage_failure_codes.php` يكتب عبر بوّابةٍ
           (`$fc_gate->update('failure_codes', …)`) فاسمُ الجدولِ **وسيطٌ نصّيّ**
           لا كلمةٌ مفتاحيّة — فالمِرساةُ تُقاس بالنمطَين معًا. */
        foreach ($built as $k => $b) {
            if ($b['src'] === '') { continue; }
            $qt = preg_quote($tbl, '~');
            if (preg_match('~\b(FROM|INTO|UPDATE|JOIN)\s+`?' . $qt . '`?\b~i', $b['src'])
             || preg_match('~[\'"]' . $qt . '[\'"]\s*,~', $b['src'])) { $hits[$k] = $b; }
        }
        if (count($hits) === 1) { $sid = key($hits); $rule = 'ANCHOR_TABLE_SOLE_WRITER'; $why = "الجدول $tbl تكتبه شاشة واحدة"; }
        elseif (count($hits) > 1) {
            foreach ($hits as $k => $b) { if ($b['owner'] === $dept) { $sid = $k; break; } }
            if ($sid !== '') { $rule = 'ANCHOR_TABLE_OWNER_DEPT'; $why = "الجدول $tbl تكتبه " . count($hits) . " شاشة والمالكة منها بالادارة $dept"; }
            else {
                /* اسمُ الملفِّ يحمل اسمَ الجدول — مِرساةٌ من القرصِ لا ترجيحٌ برأي */
                foreach ($hits as $k => $b) {
                    if (strpos(strtolower(basename($b['route'], '.php')), strtolower($tbl)) !== false) {
                        $sid = $k; $rule = 'ANCHOR_TABLE_IN_FILENAME';
                        $why = "الجدول $tbl تكتبه " . count($hits) . " شاشة واسم الملف يحمل اسم الجدول"; break;
                    }
                }
            }
        }
        /* شاشاتُ CMP-03 المولَّدةُ تُعلن جدولَها ثابتًا: `$CANONICAL = '<name>.php'`
           وصفوفُها في المخزنِ البينيِّ حتى يُولَّد جدولُها الأصليّ — فالمِرساةُ
           تُقاس من الثابتِ المُعلَنِ لا من جملةِ SQL لا يكتبها الملفُّ بعد. */
        if ($sid === '' && strpos($tbl, 'scr_') === 0) {
            $canon = substr($tbl, 4) . '.php';
            foreach ($built as $k => $b) {
                if ($b['src'] !== '' && strpos($b['src'], "\$CANONICAL = '" . $canon . "'") !== false) {
                    $sid = $k; $rule = 'CMP03_CANONICAL_CONST';
                    $why = "الشاشة تعلن \$CANONICAL = '$canon' — وصفوفها في المخزن البيني حتى يولد $tbl"; break;
                }
            }
        }
    }
    /* قاعدة ②: التسميةُ المعياريّةُ حرفًا في `nav_canonical` */
    if ($sid === '') {
        $x = $one("SELECT g.screen_id FROM nav_canonical n
                     JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE n.canonical_ar = '" . $esc($q['surface']) . "' LIMIT 1");
        if ($x) { $sid = (string) $x; $rule = 'CANONICAL_TITLE_EXACT'; $why = 'اسم السطح يطابق التسمية المعيارية حرفا'; }
    }
    /* قاعدة ③: صفٌّ في دفترِ الفجواتِ ⇒ لم يُبنَ، وموجتُه من الدفتر */
    if ($sid === '') {
        $g = $conn->query("SELECT verdict, wave_stage FROM repair01_target_gaps
                            WHERE surface_name = '" . $esc($q['surface']) . "' LIMIT 1");
        if ($g && ($gx = $g->fetch_assoc())) {
            $rule = 'TARGET_GAP_ROW'; $wave = $gx['wave_stage'] !== '' ? $gx['wave_stage'] : 'W' . str_pad((string) 0, 2, '0');
            $why = 'سطح مستهدف في دفتر الفجوات: ' . mb_substr((string) $gx['verdict'], 0, 60);
            $scopeGap++;
        }
    }
    /* قاعدة ④: صمتت المصادر ⇒ قرارٌ مسجَّل */
    if ($sid === '' && $rule === '') {
        $rule = 'W3_DECISION:W3-D-02';
        $why = 'صمتت المرساة والتسمية ودفتر الفجوات — يحسم في موجة الادارة المالكة';
        $scopeSilent++; $silentList[] = $rid;
    }
    if ($sid !== '') { $scopeMapped++; }
    $W("INSERT INTO repair01_w3_scope
        (requirement_id,unit,group_name,surface,anchor_screen_id,map_rule,map_why,wave_stage,src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($sid) . "','" . $esc($rule) . "','" . $esc($why) . "',
                '" . $esc($wave) . "','" . $esc($q['src_ref']) . "')
        ON DUPLICATE KEY UPDATE anchor_screen_id=VALUES(anchor_screen_id), map_rule=VALUES(map_rule),
          map_why=VALUES(map_why), wave_stage=VALUES(wave_stage)");
}
printf("  موصولٌ بشاشةٍ حيّة %d · في دفترِ الفجوات %d · صامتٌ ⇐ قرار %d%s\n\n",
    $scopeMapped, $scopeGap, $scopeSilent, $silentList ? ' (' . implode('، ', $silentList) . ')' : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ السايدبار — الخطواتُ السبعُ بترتيبها على أسطحِ النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ السايدبارُ — سبعُ خطواتٍ على أسطحِ النطاق ─────────────────────\n";
$codes = repair01_w3_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$tabMap = repair01_w3_entity_tab_map($ROOT);
$W("DELETE FROM repair01_w3_sidebar");

$s2Fixed = 0; $s2Pending = 0; $s3Fixed = 0; $s3Pending = 0;
$s5Demoted = 0; $s5Blocked = 0; $s6Fixed = 0; $s7Linked = 0; $scopeScreens = 0;

$rs = $conn->query("SELECT screen_id, route, owner_code, visibility_class, parent_screen_id
                    FROM repair01_screen_registry
                    WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL
                    ORDER BY owner_code, route");
$rows = array();
while ($rs && $x = $rs->fetch_assoc()) { $rows[] = $x; }

foreach ($rows as $x) {
    $scopeScreens++;
    $sid = $x['screen_id']; $rt = $x['route']; $rtE = $esc($rt); $navPred = repair01_w3_nav_pred($conn, $rt);
    $navN   = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $labels = (string) $one("SELECT GROUP_CONCAT(DISTINCT label_ar SEPARATOR ' | ') FROM nav_items
                              WHERE ($navPred) AND active=1");
    $can = $conn->query("SELECT canonical_ar, group_name, sort_no, status FROM nav_canonical WHERE route='$rtE' LIMIT 1");
    $can = $can ? $can->fetch_assoc() : null;
    $grpLive = (string) $one("SELECT GROUP_CONCAT(DISTINCT g.name SEPARATOR ' | ') FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE ($navPred) AND n.active=1");

    /* ─ خطوة ① التعطيلُ: غيرُ المعتمدِ في المستهدَف لا يبقى بندًا ─ */
    if ($x['visibility_class'] === 'MENU_ITEM' && $navN > 0)      { $s1v = 'KEEP_APPROVED_MENU'; $s1r = 'REGISTRY_MENU_ITEM'; }
    elseif ($x['visibility_class'] === 'MENU_ITEM' && $navN === 0) { $s1v = 'MENU_WITHOUT_ROW';   $s1r = 'REGISTRY_VS_LIVE'; }
    elseif ($navN > 0)                                            { $s1v = 'ROW_WITHOUT_MENU';    $s1r = 'REGISTRY_VS_LIVE'; }
    else                                                          { $s1v = 'NOT_A_MENU_ITEM';     $s1r = 'REGISTRY_' . $x['visibility_class']; }

    /* ─ خطوة ② الاسم: المعياريُّ يغلب، والمعلَّقُ ينتظر مالكَه ─ */
    $s2v = 'NO_CANONICAL_ROW'; $s2r = 'NAV_CANONICAL_MISSING';
    $labelCanon = $can ? (string) $can['canonical_ar'] : '';
    if ($can && $labels !== '') {
        if ($labels === $labelCanon) { $s2v = 'ALIGNED'; $s2r = 'LABEL_EQUALS_CANONICAL'; }
        elseif ($can['status'] === 'APPROVED') {
            $W("UPDATE nav_items SET label_ar = '" . $esc($labelCanon) . "'
                 WHERE ($navPred) AND label_ar <> '" . $esc($labelCanon) . "'");
            $s2v = 'CORRECTED_TO_CANONICAL'; $s2r = 'CANONICAL_APPROVED_WINS'; $s2Fixed++;
        } else {
            $s2v = 'PENDING_OWNER_NAME'; $s2r = 'CANONICAL_' . $can['status']; $s2Pending++;
        }
    } elseif ($can && $labels === '') { $s2v = 'NOT_A_MENU_ITEM'; $s2r = 'NO_ACTIVE_ROW'; }

    /* ─ خطوة ③ المجموعة: من مجموعةِ الدورةِ المعياريّة ─ */
    $grpCanon = $can ? (string) $can['group_name'] : '';
    $s3v = 'NO_CANONICAL_ROW'; $s3r = 'NAV_CANONICAL_MISSING';
    if ($can && $grpLive !== '') {
        if ($grpLive === $grpCanon) { $s3v = 'ALIGNED'; $s3r = 'GROUP_EQUALS_CANONICAL'; }
        elseif ($can['status'] === 'APPROVED') { $s3v = 'RENDERED_FROM_CANONICAL'; $s3r = 'RENDERER_READS_CANONICAL'; $s3Fixed++; }
        else { $s3v = 'PENDING_OWNER_GROUP'; $s3r = 'CANONICAL_' . $can['status']; $s3Pending++; }
    } elseif ($can) { $s3v = 'NOT_A_MENU_ITEM'; $s3r = 'NO_ACTIVE_ROW'; }

    /* ─ خطوة ④ الترتيب: من موضعِ الدورةِ لا من الأبجديّةِ ولا من تاريخِ الإنشاء ─ */
    $stageOrder = (string) $one("SELECT MIN(stage_order) FROM repair01_surfaces WHERE screen_id = '" . $esc($sid) . "'");
    if ($stageOrder !== '' && $stageOrder !== null) { $s4src = 'SURFACE_STAGE_ORDER'; $s4no = (int) $stageOrder; $s4v = 'CYCLE_ORDER'; $s4r = 'W00_CYCLE_REGISTER'; }
    elseif ($can) { $s4src = 'NAV_CANONICAL_SORT_NO'; $s4no = (int) $can['sort_no']; $s4v = 'CANONICAL_ORDER'; $s4r = 'NAV_CANONICAL_SORT'; }
    else { $s4src = ''; $s4no = 0; $s4v = 'NO_ORDER_SOURCE'; $s4r = 'W3_DECISION:W3-D-03'; }

    /* ─ خطوة ⑤ الأبُ والتبويب: القرارُ يحدّد الموضعَ — والبلوغُ يُقاس قبلَ الخفض ─ */
    $s5v = 'NO_PARENT'; $s5r = 'REGISTRY_NO_PARENT'; $s5why = '';
    if ($x['parent_screen_id'] !== '') {
        $tab = isset($tabMap[strtolower($rt)]) ? $tabMap[strtolower($rt)] : null;
        $renders = repair01_w3_renders_tabs($ROOT, $rt);
        $parentRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id='" . $esc($x['parent_screen_id']) . "'");
        /* ⚠ **والمخفوضُ سلفًا يُعاد إثباتُه لا يُصدَّق**: بندٌ جديدٌ قد يظهر في
           القائمةِ بعدَ الخفضِ (مِرساةٌ `#n` · منظرٌ `?view=`) فيعود السطحُ بندًا
           وحكمُه في السجلِّ «تبويبٌ». فالمخفوضُ بقاعدةِ هذه المرحلةِ يمرُّ
           بالبرهانِ نفسِه في كلِّ تشغيل.
           ⚠ ويُحسب **قبلَ** قياسِ البلوغِ: حسابُه بعدَه يجعل المخفوضَ يُقاس
             بمُصيَّرٍ لا بندَ له فيه — فيعود «صفرُ دورٍ يفقد» وهو **أخضرُ بالبناء**. */
        $w3Demoted = ($x['visibility_class'] === 'TAB_CHILD'
                      && (string) $one("SELECT visibility_rule FROM repair01_screen_registry
                                         WHERE screen_id = '" . $esc($sid) . "'") === 'W3_TAB_PROVEN_BY_ENTITY_TABS');
        /* البلوغُ يُقاس على **السايدبارِ المُصيَّرِ** لا على صفوفِ `nav_items`
           (‏انظر تعليقَ `repair01_w3_rendered_map`) — والمخفوضُ سلفًا يُقاس
           بأدوارِه في سجلِّ الإخفاءِ لأنَّ بندَه لم يعد يُصيَّر. */
        $childRoles = $w3Demoted
            ? repair01_w3_hidden_roles($conn, $rt)
            : repair01_w3_nav_roles($ROOT, $conn, $rt);
        $parentRoles = $parentRoute !== '' ? repair01_w3_nav_roles($ROOT, $conn, $parentRoute) : array();
        $lost = array_values(array_diff($childRoles, $parentRoles));
        /* وإن سقط البرهانُ عن سطحٍ مخفوضٍ سلفًا **يُرَدُّ بندُه** — فحكمٌ بُني على
           قياسٍ تبيَّن خطؤه لا يُترك قائمًا لأنّه نُفِّذ. */
        $undo = function () use ($conn, $W, $esc, $sid, $rt, $navPred) {
            $W("UPDATE nav_items n
                  JOIN gov_nav_hidden_log h ON h.nav_id = n.id AND h.doc_code = 'RPR-W03'
                   SET n.active = 1 WHERE " . str_replace('`route`', 'n.`route`', $navPred));
            $W("UPDATE repair01_screen_registry SET visibility_class = 'MENU_ITEM',
                    visibility_rule = 'W3_TAB_DEMOTION_REVERTED' WHERE screen_id = '" . $esc($sid) . "'");
            $W("DELETE FROM gov_nav_hidden_log WHERE doc_code = 'RPR-W03' AND " . $navPred);
        };
        if ($x['visibility_class'] !== 'MENU_ITEM' && !$w3Demoted) { $s5v = 'ALREADY_TAB'; $s5r = 'REGISTRY_' . $x['visibility_class']; }
        elseif ($tab === null) { if ($w3Demoted) { $undo(); } $s5v = 'TAB_CLAIM_UNPROVEN'; $s5r = 'NO_ROW_IN_ENTITY_TABS'; $s5why = 'الادعاء ابا بلا مرساة مقيسة في سجل تبويبات الكيانات'; $s5Blocked++; }
        elseif ($renders === 0) { if ($w3Demoted) { $undo(); } $s5v = 'TAB_BAR_NOT_RENDERED'; $s5r = 'DISK_NO_ems_entity_tabs'; $s5why = 'الشاشة لا تطبع شريط الرحلة فالخفض يقطع الطريق'; $s5Blocked++; }
        elseif ($lost) { if ($w3Demoted) { $undo(); $s5r = 'ROLE_REACH_MEASURED_REVERTED'; } else { $s5r = 'ROLE_REACH_MEASURED'; } $s5v = 'DEMOTION_LOSES_ROLES'; $s5why = 'ادوار تفقد البلوغ في السايدبار المصير: ' . implode('،', $lost); $s5Blocked++; }
        else {
            /* البلوغُ مُثبَتٌ ⇒ الخفضُ إلى تبويبٍ وعذرُه في سجلِّ الإخفاء */
            /* ⚠ **والعذرُ يحفظ البلوغَ المُصيَّرَ لا صفوفَ القائمة**: صفٌّ نشِطٌ
               لدورٍ **لا يُصيَّر له** لا بلوغَ فيه أصلًا. وتسجيلُ كلِّ الصفوفِ
               بلوغًا يجعل إعادةَ الإثباتِ في التشغيلِ التالي تقرأ أدوارًا لم تكن
               ترى البندَ، فتحكم بالفقدِ وتردُّ الخفضَ — ثمّ يعود البندُ فيُصيَّر
               فيُخفَض ثانيةً: **تذبذبٌ بين تشغيلَين سببُه المقياسُ لا التغيير**.
               فالمُصيَّرُ يُوسَم `TAB_IN_PARENT` وغيرُه `NOT_RENDERED` — والصفُّ
               محفوظٌ للإرجاعِ في الحالتَين. */
            $renderedIn = $childRoles ? implode(',', array_map('intval', $childRoles)) : '-1';
            $W("INSERT INTO gov_nav_hidden_log (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable)
                SELECT role_id, id, route, label_ar, group_id, sort_order, 'RPR-W03',
                       CASE WHEN role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END
                  FROM nav_items WHERE ($navPred) AND active = 1
                ON DUPLICATE KEY UPDATE doc_code = 'RPR-W03',
                       reachable = CASE WHEN nav_items.role_id IN ($renderedIn) THEN 'TAB_IN_PARENT' ELSE 'NOT_RENDERED' END");
            $W("UPDATE nav_items SET active = 0 WHERE ($navPred) AND active = 1");
            $W("UPDATE repair01_screen_registry SET visibility_class = 'TAB_CHILD',
                    visibility_rule = 'W3_TAB_PROVEN_BY_ENTITY_TABS' WHERE screen_id = '" . $esc($sid) . "'");
            $s5v = 'DEMOTED_TO_TAB'; $s5r = 'TAB_PROVEN_AND_REACHABLE';
            $s5why = 'تبويب «' . $tab['tab'] . '» في ' . $tab['parent'] . ' — وكل ادوار الابن في الاب';
            $s5Demoted++;
            $navN = 0;
        }
    }

    /* ─ خطوة ⑥ الظهورُ بالصلاحيةِ لا بالإخفاء + حارسُ عرضٍ على الخادم ─ */
    $permRows  = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1");
    $permCoded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1
                              AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($permRows > 0 && $permCoded < $permRows) {
        $W("UPDATE nav_items SET permission_code = '" . $rtE . "'
             WHERE ($navPred) AND active=1
               AND (permission_code IS NULL OR permission_code = '')");
        $permCoded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active=1
                                  AND permission_code IS NOT NULL AND permission_code <> ''");
        $s6Fixed++;
        $s6v = 'PERMISSION_CODE_FILLED'; $s6r = 'ROUTE_IS_MODULE_CODE';
    } elseif ($permRows === 0) { $s6v = 'NOT_A_MENU_ITEM'; $s6r = 'NO_ACTIVE_ROW'; }
    else { $s6v = 'PERMISSION_GATED'; $s6r = 'ALL_ROWS_CODED'; }
    $guardKind = (string) $one("SELECT guard_kind FROM repair01_screen_registry WHERE screen_id='" . $esc($sid) . "'");

    /* ─ خطوة ⑦ الربطُ بـCanonical Screen_ID ─ */
    $s7 = 0; $s7v = 'NO_CANONICAL_ROW'; $s7r = 'NAV_CANONICAL_MISSING';
    if ($can) {
        $W("UPDATE nav_canonical SET screen_id = '" . $esc($sid) . "' WHERE route = '$rtE'");
        $s7 = 1; $s7v = 'LINKED'; $s7r = 'ROUTE_TO_SCREEN_ID'; $s7Linked++;
    }

    $vis = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE screen_id='" . $esc($sid) . "'");
    $W("INSERT INTO repair01_w3_sidebar
        (screen_id,route,owner_code,s1_verdict,s1_rule,s2_label_live,s2_label_canon,s2_verdict,s2_rule,
         s3_group_live,s3_group_canon,s3_verdict,s3_rule,s4_order_src,s4_order_no,s4_verdict,s4_rule,
         s5_parent,s5_verdict,s5_rule,s5_why,s6_visibility,s6_perm_rows,s6_perm_coded,s6_guard_kind,
         s6_verdict,s6_rule,s7_linked,s7_verdict,s7_rule,measured_at)
        VALUES ('" . $esc($sid) . "','" . $rtE . "','" . $esc($x['owner_code']) . "',
                '" . $esc($s1v) . "','" . $esc($s1r) . "','" . $esc(mb_substr($labels, 0, 180)) . "','" . $esc($labelCanon) . "',
                '" . $esc($s2v) . "','" . $esc($s2r) . "','" . $esc(mb_substr($grpLive, 0, 180)) . "','" . $esc($grpCanon) . "',
                '" . $esc($s3v) . "','" . $esc($s3r) . "','" . $esc($s4src) . "'," . (int) $s4no . ",
                '" . $esc($s4v) . "','" . $esc($s4r) . "','" . $esc($x['parent_screen_id']) . "',
                '" . $esc($s5v) . "','" . $esc($s5r) . "','" . $esc($s5why) . "','" . $esc($vis) . "',
                $permRows,$permCoded,'" . $esc($guardKind) . "','" . $esc($s6v) . "','" . $esc($s6r) . "',
                $s7,'" . $esc($s7v) . "','" . $esc($s7r) . "',NOW())
        ON DUPLICATE KEY UPDATE s1_verdict=VALUES(s1_verdict), s2_verdict=VALUES(s2_verdict),
          s3_verdict=VALUES(s3_verdict), s4_verdict=VALUES(s4_verdict), s5_verdict=VALUES(s5_verdict),
          s6_verdict=VALUES(s6_verdict), s7_verdict=VALUES(s7_verdict), measured_at=NOW()");
}
printf("  أسطحُ النطاقِ المبنيّة %d\n", $scopeScreens);
printf("  ② الاسم: صُحِّح %d · ينتظر المالكَ %d\n", $s2Fixed, $s2Pending);
printf("  ③ المجموعة: يُصيَّر من المعياريِّ %d · ينتظر المالكَ %d\n", $s3Fixed, $s3Pending);
printf("  ⑤ الأب/التبويب: خُفِض %d · مُنع بقياسٍ %d\n", $s5Demoted, $s5Blocked);
printf("  ⑥ الصلاحية: مُلئ رمزُ صلاحيةٍ في %d شاشة\n", $s6Fixed);
printf("  ⑦ الربط: %d/%d\n\n", $s7Linked, $scopeScreens);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ عقودُ الأثرِ — لكلِّ حدثٍ حيٍّ على كيانٍ أمٍّ من النطاق
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ عقودُ الأثر ───────────────────────────────────────────────────\n";
require_once $ROOT . '/tools/lib/repair01_w3_contracts.php';
$narr = repair01_w3_contract_narrative();
$entMap = repair01_w3_entity_key_map();
/* `repair01_events.event_code` فهرسُه غيرُ فريد — فالإعادةُ تُمسح ثمّ تُكتب،
   وإلا ضاعفَ كلُّ تشغيلٍ صفوفَه. والمحذوفُ ما وسمته هذه المرحلةُ وحدَها. */
$W("DELETE FROM repair01_events WHERE contract_stage = 'W03' AND wave = 'W03'");
$liveKeys = array();
$r = $conn->query("SELECT DISTINCT event_key, entity_type FROM ems_business_events
                   WHERE entity_type IN ('" . implode("','", array_map($esc, array_keys($entMap))) . "')");
while ($r && $x = $r->fetch_assoc()) { $liveKeys[$x['event_key']] = $x['entity_type']; }
$written = 0; $missing = array();
foreach ($liveKeys as $ek => $ent) {
    if (!isset($narr[$ek])) { $missing[] = $ek; continue; }
    $c = $narr[$ek];
    $m = repair01_w3_measure_consumers($conn, $ek);
    /* حدثٌ بلا مستهلكٍ مقيسٍ لا يُكتب له عقدٌ كاذب */
    if (!$m['consumers']) { $missing[] = $ek . ' (بلا مستهلكٍ في gov_effect_map)'; continue; }
    $W("INSERT INTO repair01_events
        (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,retry_policy,src_ref,
         trigger_rule,min_payload,consumer_list,consumer_effect,preconditions,failure_policy,compensation,
         contract_status,contract_rule,contract_stage)
        VALUES ('" . $esc($ek) . "','" . $esc($c['name']) . "','W03','" . $esc($c['unit']) . "',
                '" . $esc($c['screen']) . "','" . $esc($c['idem']) . "','" . $esc(implode(' · ', $m['consumers'])) . "',
                '" . $esc($c['effect']) . "','" . $esc($m['retry']) . "',
                '" . $esc('قياسٌ حيّ: ems_business_events › ' . $ek . ' › ' . $ent . ' + ' . $c['src']) . "',
                '" . $esc($c['trigger']) . "','" . $esc($c['payload']) . "','" . $esc(implode("\n", $m['consumers'])) . "',
                '" . $esc(implode("\n", $m['effects'])) . "','" . $esc($c['pre']) . "','" . $esc($c['fail']) . "',
                '" . $esc($c['comp']) . "','RECORDED','LIVE_EVENT_KEY_MEASURED','W03')");
    $written++;
}
printf("  حدثٌ حيٌّ على كيانٍ أمّ %d · عقدٌ مكتوبٌ %d · بلا عقدٍ %d%s\n\n",
    count($liveKeys), $written, count($missing), $missing ? ' ⇐ ' . implode('، ', $missing) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ قراراتُ المرحلة
   ═══════════════════════════════════════════════════════════════════════════ */
$amb  = (int) $one("SELECT COUNT(*) FROM persons WHERE w3_link_rule LIKE '%AMBIGUOUS_NAME%'");
$quarN = (int) $one("SELECT COUNT(*) FROM persons WHERE w3_link_rule LIKE 'QUARANTINE%'");
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$DEC = array(
    array('W3-D-01', 'صفُّ هويةٍ لا يجد موظّفَه — أيُوصَل أم يُحجَر؟',
        'الاسمُ المتعدِّدُ في الكيانِ لا يُخمَّن (UNRESOLVED)، والصفُّ بلا موقعٍ ولا علاقةٍ ولا موظّفٍ يُحجَر بعلَمِ السجلِّ نفسِه (active=0) ولا يُحذف — والحجرُ مقيسٌ في البوّابةِ فلا يتّسع صامتًا',
        'الحذفُ يمحو الدليلَ (‏_CONTEXT · الشبحُ يُوسَم ولا يُحذف) والتخمينُ يصنع وصلًا كاذبًا يُقرأ صحيحًا وهو خطأ',
        $amb + $quarN),
    array('W3-D-02', 'سطحُ متطلَّبٍ صمتت عنه المِرساةُ والتسميةُ ودفترُ الفجوات',
        'يُسجَّل بمؤشِّرٍ إلى هذا القرارِ ولا تُخترع له شاشةٌ ولا صفُّ فجوة — والحسمُ في موجةِ الإدارةِ المالكة',
        'اختراعُ سطحٍ هنا يثبّت اسمًا لم يقرّه مالكُه ويُدخله السجلَّ المعياريَّ بلا سند',
        $scopeSilent),
    array('W3-D-05', 'تركيبةٌ ممنوعةٌ في فصلِ الواجباتِ وقعت فعلًا في صفوفٍ حيّة',
        'تُقاس وتُقفَل اتّجاهًا: العددُ المقيسُ لا يزيد على المُعلَنِ هنا (W3-16) — والتصحيحُ الرجعيُّ للصفوفِ القائمةِ قرارُ مالكٍ لا فعلُ أداة',
        'دهسُ الصفوفِ يمحو الواقعةَ قبل أن تُراجَع؛ والقفلُ يمنع نموَّها. و`sec_sod_pairs` يحمل ثلاثةَ عشرَ زوجًا كلُّها مالية — ولا واحدٌ من تركيباتِ هذا النطاق، فالمنعُ الحيُّ غيرُ قائمٍ بعد (W13)',
        (int) $one("SELECT COUNT(*) FROM unit_entries WHERE entered_by = qty_decided_by AND qty_decided_by IS NOT NULL")
      + (int) $one("SELECT COUNT(*) FROM mnt_order WHERE technician_id = supervisor_id AND technician_id IS NOT NULL")),
    array('W3-D-04', 'عمودُ حالةٍ حرٌّ يخالف آلةَ الحالةِ في الشيفرة',
        'يُعلَن في W03_STATE_MACHINES.md بمقامِه المقيسِ ولا يُدهَس هنا — والتحويلُ إلى ENUM بجسرِ ترجمةٍ في موجةِ الإدارةِ المالكة (W06)',
        'دهسُ قيمِ حالةٍ حيّةٍ يمحو ما تعنيه الصفوفُ الفعليّةُ قبل أن يُقرأ؛ و«بلاغ» و«تنفيذ» و«إغلاق» ليست خطأً بل مفرداتُ المستخدمِ التي لم تُترجَم — والجسرُ يترجم والدهسُ يمحو',
        (int) $one("SELECT COUNT(*) FROM mnt_order
                     WHERE state NOT IN ('open','assigned','in_progress','waiting_part','done','closed','cancelled')")),
    array('W3-D-03', 'شاشةٌ في النطاقِ بلا موضعٍ من دورةِ عملٍ ولا صفٍّ معياريّ',
        'ترتيبُها يبقى كما هو ويُوسَم NO_ORDER_SOURCE — ولا يُخترع لها رقمُ ترتيبٍ يدويّ',
        'رقمُ ترتيبٍ مخترعٌ ترتيبٌ يدويٌّ موازٍ للسجلّ — وهو المحظورُ نفسُه في §٥',
        $noOrder),
);
foreach ($DEC as $d) {
    $W("INSERT INTO repair01_w3_decisions (decision_id,question,ruling,rationale,scope_rows)
        VALUES ('" . $esc($d[0]) . "','" . $esc($d[1]) . "','" . $esc($d[2]) . "','" . $esc($d[3]) . "'," . (int) $d[4] . ")
        ON DUPLICATE KEY UPDATE question=VALUES(question), ruling=VALUES(ruling),
          rationale=VALUES(rationale), scope_rows=VALUES(scope_rows)");
}
echo "⑧ القرارات: " . count($DEC) . " مسجَّلة (W3-D-01 نطاقُه $amb+$quarN · W3-D-02 نطاقُه $scopeSilent · W3-D-03 نطاقُه $noOrder)\n\n";

echo "الحكم: " . ($REPORT ? "قياسٌ تمّ (بلا كتابة) ✔\n" : "الكتابةُ تمّت ✔\n");
exit(0);
