<?php
/**
 * tools/repair01_w13_journey.php — رحلةُ العامل (‏W13 §٦-أ · §28)
 * ═══════════════════════════════════════════════════════════════════════════
 * **تعيينٌ ← تأهيلٌ وجاهزيّة ← إسنادٌ لموقع ← حضورٌ فعليّ ← أداء ← بلاغٌ يخصّه
 *   يُنشئه مُبلِّغٌ غيرُه ← توجيهٌ لمالكِ الحلِّ في إدارتِه المختصّة ← تصعيدٌ عند
 *   التجاوز ← تحقّق ← إغلاق ← تسويةُ مستحقّاتِه — وأطرافُ التذكرةِ الأربعةُ
 *   أشخاصٌ متمايزون في السجلّ.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — ترشُّحٌ يصير موظّفًا · مباشرةٌ تفتح بصفرِ بندٍ
 *   معلَّق · بلاغٌ بأربعةِ أطرافٍ متمايزين · توجيهٌ يفتح الإسناد · إجراءٌ يفتح
 *   إعلانَ المعالجة · نافذةٌ تفتح بمدّتِها من السجلّ · تحقّقٌ يفتح الإغلاق ·
 *   إغلاقٌ يفتح إعادةَ الفتح · قرارُ قضيّةٍ يفتح الخصمَ · تصفيةٌ تُعتمَد بإبراءٍ.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «المُبلِّغُ ليس محلَّ البلاغ» · «مالكُ الحلِّ
 *   ليس مركزَ البلاغات» · «لا توجيهَ للمركزِ كمالكِ حلّ» · «لا إسنادَ للمركز» ·
 *   «فاعلٌ واحدٌ لا يشغل دورَين» · «لا معالجةَ بلا إجراء» · «لا تحقّقَ من
 *   المنفِّذِ نفسِه» · «لا إغلاقَ آليَّ للحرِج» · «لا إغلاقَ بلا تحقّق» · «من بلَّغ
 *   لا يحقّق» · «من حقّق لا يقرّر» · «لا خصمَ بلا قرارِ قضيّة» · «من طلب الحركةَ
 *   لا يعتمدها» · «لا تصفيةَ بلا إبراءِ عهدة» — تُقاس **بالاستدعاءِ الفعليِّ
 *   ورمزِ الرفض**.
 *
 * ⚠ **والنظافةُ كنسٌ بالوسمِ لا إرجاعٌ بمعاملة**: خدماتُ الدورةِ قد تفتح
 *   معاملةً داخليّةً فيثبّت MySQL الخارجيّةَ ضمنًا (‏درسُ W09). فكلُّ صفٍّ
 *   تكتبه الرحلةُ يحمل وسمَ عائلتِها، والكنسُ يمسح **بالوسم** — ويُشغَّل مرّتَين.
 *
 * التشغيل: php tools/repair01_w13_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

/* ⚠ **حارسُ الموتِ الصامت**: `config.php` يبتلع مخرَجَ سطرِ الأوامر، فرحلةٌ
     تسقط بخطإٍ قاتلٍ تخرج بلا سطرٍ واحدٍ ويقرأ القارئُ صمتًا لا سببًا. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\n✘ سقطت الرحلةُ بخطإٍ قاتل:\n   " . $e['message']
                     . "\n   في " . $e['file'] . ':' . $e['line'] . "\n");
    }
});

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w13_scan.php';
require_once $ROOT . '/app/Services/People/PeopleCycleService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\People\PeopleCycleService as PPL;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w13_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بدقّةِ الميكروثانية — جولتانِ في الثانيةِ نفسِها تتقاسمان
   المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (‏درسُ W04). */
$RUN = 'W13J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$TAG = 'W13J';

echo "═══════════ رحلةُ العامل — REPAIR01 · W13 §٦-أ ═══════════\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$log = function ($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed, $co = 0)
       use (&$ST) {
    $ST[] = array($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state,
                  $passed ? 1 : 0, (int) $co);
};

/* ══════════════════════════════════════════════════════════════════════════
   كنسُ العائلةِ — يُشغَّل قبلَ البدءِ وبعدَ النهاية
   ══════════════════════════════════════════════════════════════════════════ */
$sweep = function () use ($conn, $TAG) {
    $q = array(
        "DELETE FROM tkt_reopen WHERE src_ref LIKE '$TAG%' OR note LIKE '$TAG%'",
        "DELETE FROM tkt_verification WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM tkt_resolution_action WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM tkt_assignment_history WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM tkt_routing_history WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM tkt_party WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM ticket_communications WHERE note LIKE '$TAG%'",
        "DELETE FROM ticket_escalations WHERE ws_id IN
            (SELECT ws_id FROM ticket_workstreams WHERE workstream_type LIKE '$TAG%')",
        "DELETE FROM ticket_workstreams WHERE workstream_type LIKE '$TAG%'",
        "DELETE FROM tkt_party WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tkt_routing_history WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tkt_assignment_history WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tkt_resolution_action WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tkt_verification WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tkt_reopen WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM ticket_communications WHERE tk_id IN (SELECT id FROM tickets WHERE ticket_no LIKE '$TAG%')",
        "DELETE FROM tickets WHERE ticket_no LIKE '$TAG%'",
        "DELETE FROM payroll_deductions WHERE note LIKE '$TAG%'",
        "DELETE FROM hr_disciplinary_stage WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM hr_disciplinary_case WHERE case_no LIKE '$TAG%'",
        "DELETE FROM hr_benefit_enrollment WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM hr_performance_review WHERE cycle_code LIKE '$TAG%'",
        "DELETE FROM hr_training_record WHERE program_code LIKE '$TAG%'",
        "DELETE FROM hr_job_movement WHERE doc_ref LIKE '$TAG%'",
        "DELETE FROM hr_onboarding_item WHERE src_ref LIKE '$TAG%'",
        "DELETE FROM hr_employee_document WHERE doc_no LIKE '$TAG%'",
        "DELETE FROM attendance_days WHERE reference_doc LIKE '$TAG%'",
        "DELETE FROM rec_stage_log WHERE note LIKE '$TAG%'",
        "DELETE FROM rec_applications WHERE applicant_name LIKE '$TAG%'",
        "DELETE FROM rec_vacancies WHERE vacancy_no LIKE '$TAG%'",
        "DELETE FROM employee_final_settlements WHERE note LIKE '$TAG%'",
        "DELETE FROM employee_contracts WHERE signed_file_ref LIKE '$TAG%'",
        "DELETE FROM employees WHERE employee_code LIKE '$TAG%'",
        "DELETE FROM ems_business_events WHERE idempotency_key LIKE 'w13:%'
            AND source_ref = 'PeopleCycleService'",
    );
    foreach ($q as $s) { @$conn->query($s); }
};
$sweep();

/* ══════════════════════════════════════════════════════════════════════════
   الأرضيّة — كيانٌ قانونيٌّ واحدٌ وفاعلونَ متمايزون
   ══════════════════════════════════════════════════════════════════════════
   ◆ **وأطرافُ التذكرةِ أشخاصٌ متمايزون في السجلّ** — فالأرضيّةُ تُخرِج أربعةَ
     مفاتيحَ مختلفةٍ فعلًا، والرحلةُ تشترط تمايزَها قبل أن تبدأ.
   ══════════════════════════════════════════════════════════════════════════ */
$company = (int) $one("SELECT company_id FROM employees WHERE company_id > 0
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { echo "✘ أرضيّةٌ ناقصة: لا كيانَ قانونيٌّ في سجلِّ الموظّفين\n"; exit(1); }

$actors = array();
$ra = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 12");
while ($ra && $ax = $ra->fetch_row()) { $actors[] = (int) $ax[0]; }
if (count($actors) < 8) {
    echo "✘ أرضيّةٌ ناقصة: أقلُّ من ثمانيةِ فاعلين متمايزين في الكيان $company\n"; exit(1);
}
list($A_HRSPEC, $A_HRMGR, $A_AUTH, $A_REPORTER, $A_CENTER, $A_SOLVER, $A_VERIFIER, $A_INVEST) =
    array_slice($actors, 0, 8);
$A_DECIDER = isset($actors[8]) ? $actors[8] : $A_AUTH;

$ticketType = (int) $one("SELECT id FROM ticket_types WHERE company_id = $company ORDER BY id LIMIT 1");
if ($ticketType <= 0) { $ticketType = (int) $one("SELECT id FROM ticket_types ORDER BY id LIMIT 1"); }
$ownerRole  = (int) $one("SELECT id FROM roles ORDER BY id LIMIT 1");
if ($ticketType <= 0 || $ownerRole <= 0) {
    echo "✘ أرضيّةٌ ناقصة: لا نوعَ بلاغٍ أو لا دورَ في القاعدة\n"; exit(1);
}
$subjType = (string) $one("SELECT type_code FROM tkt_subject_type
                            WHERE company_id = $company AND type_code = 'EMPLOYEE' AND active = 1 LIMIT 1");
if ($subjType === '') {
    echo "✘ أرضيّةٌ ناقصة: كتالوجُ محلِّ البلاغِ خالٍ — شغّلْ tools/repair01_w13_apply.php\n"; exit(1);
}

PPL::setCompany($company);
PPL::setEventConnection($conn);
PPL::setThresholdConnection($conn);
if (PPL::threshold('TKT_VERIFY_WINDOW_NORMAL_H') === null) {
    echo "✘ أرضيّةٌ ناقصة: العتباتُ غيرُ مسجَّلة — شغّلْ tools/repair01_w13_apply.php\n"; exit(1);
}

\App\Core\TenantContext::class;
$G = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $A_HRSPEC, '', true));

echo "الأرضيّة: كيان=$company · فاعلون=" . count($actors)
   . " · مبلغ=$A_REPORTER · مركز=$A_CENTER · مالك حل=$A_SOLVER · متحقق=$A_VERIFIER\n\n";

$FAIL = 0;
$must = function ($ok) use (&$FAIL) { if (!$ok) { $FAIL++; } return $ok; };

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ① · التعيين — من الشاغرِ إلى الموظّف
   ══════════════════════════════════════════════════════════════════════════ */
$vacId = (int) $G->insert('rec_vacancies', array(
    'vacancy_no' => $TAG . '-VAC', 'title_text' => 'مشغل معدة', 'headcount' => 1,
    'reason' => 'خطة القوى العاملة المعتمدة', 'state' => 'open', 'created_by' => $A_HRSPEC,
));
$log('التعيين', 'فتح شاغر بموجب خطة القوى', 'rec_vacancies', 'Workforce/recruitment_pipeline.php',
     'شاغر واحد مفتوح بسببه', 'vac_id=' . $vacId,
     'الشاغر يقرا مفتوحا في مسار التوظيف فيقبل الترشح', 'open',
     $must($vacId > 0), $company);

$appId = (int) $G->insert('rec_applications', array(
    'vac_id' => $vacId, 'applicant_name' => $TAG . ' مرشح الرحلة',
    'applicant_phone' => '0000000000', 'stage' => 'received',
));
$appOnVac = (int) $G->count('rec_applications', array('where' => array('vac_id' => $vacId)));
$log('التعيين', 'ترشح تحت الشاغر لا حقل في الشاغر', 'rec_applications', 'Workforce/rec_applications.php',
     'الشاغر يحمل ترشحا واحدا على الاقل', 'ترشحات الشاغر=' . $appOnVac,
     'سطح الترشحات يقرا الشاغر ومرشحيه منفصلين', 'received',
     $must($appId > 0 && $appOnVac === 1), $company);

$stages = array(array('received', 'screening'), array('screening', 'interview'),
                array('interview', 'offer'), array('offer', 'contracting'));
$stageN = 0;
foreach ($stages as $sg) {
    $G->insert('rec_stage_log', array(
        'app_id' => $appId, 'from_stage' => $sg[0], 'to_stage' => $sg[1],
        'by_person' => $A_HRSPEC, 'note' => $TAG . ' انتقال مرحلة', 'at' => date('Y-m-d H:i:s'),
    ));
    $G->update('rec_applications', array('stage' => $sg[1]), array('app_id' => $appId));
    $stageN++;
}
$loggedStages = (int) $one("SELECT COUNT(*) FROM rec_stage_log WHERE app_id = $appId");
$log('التعيين', 'كل مرحلة سطر بنتيجتها ومقيمها', 'rec_stage_log', 'Workforce/rec_stages.php',
     'اربع مراحل مسجلة بلا قفز', 'مراحل مسجلة=' . $loggedStages,
     'سطح المراحل يقرا مسار المرشح كاملا لا حالته الاخيرة وحدها', 'contracting',
     $must($loggedStages === 4), $company);

$empId = (int) $G->insert('employees', array(
    'employee_code' => $TAG . '-EMP', 'name' => $TAG . ' عامل الرحلة',
    'employee_type' => 'operator', 'employee_status' => 'نشط',
    'start_date' => date('Y-m-d'), 'is_workforce' => 1,
));
$G->update('rec_applications', array('employee_id' => $empId, 'stage' => 'onboarded'),
           array('app_id' => $appId));
$linked = (int) $one("SELECT COUNT(*) FROM rec_applications WHERE app_id = $appId AND employee_id = $empId");
$log('التعيين', 'الترشح يصير موظفا بمرجعه', 'employees', 'Employees/employees.php',
     'الترشح يحمل مفتاح الموظف الناتج', 'ترشح مربوط=' . $linked,
     'سجل الموظفين يقرا مصدر التعيين فلا يظهر الموظف بلا مسار', 'onboarded',
     $must($empId > 0 && $linked === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ② · التأهيلُ والجاهزيّة — مستندٌ وتدريبٌ وتهيئة
   ══════════════════════════════════════════════════════════════════════════ */
$docR = PPL::addEmployeeDocument($G, array(
    'employee_id' => $empId, 'doc_type' => 'license', 'doc_no' => $TAG . '-LIC',
    'issued_at' => date('Y-m-d'), 'expires_at' => date('Y-m-d', strtotime('+1 year')),
    'is_mandatory' => 1, 'file_ref' => 'uploads/' . $TAG . '-lic.pdf', 'src_ref' => $TAG,
), $A_HRSPEC);
$docN = (int) $G->count('hr_employee_document', array('where' => array('employee_id' => $empId)));
$log('التأهيل', 'مستند الزامي بصلاحيته', 'hr_employee_document', 'Employees/hr_employee_documents.php',
     'مستند واحد بتاريخ انتهاء', 'مستندات=' . $docN,
     'سطح المستندات يقرا صلاحية الرخصة فينبه قبل انتهائها', 'valid',
     $must($docR['ok'] && $docN === 1), $company);

$badDoc = PPL::addEmployeeDocument($G, array(
    'employee_id' => $empId, 'doc_type' => 'medical', 'doc_no' => $TAG . '-MED',
    'is_mandatory' => 1, 'file_ref' => 'uploads/' . $TAG . '-med.pdf', 'src_ref' => $TAG,
), $A_HRSPEC);
$log('التأهيل', 'مستند الزامي بلا تاريخ انتهاء يرد', 'hr_employee_document', 'PeopleCycleService',
     'رمز الرد MANDATORY_DOC_WITHOUT_EXPIRY', 'الرمز=' . $badDoc['code'],
     'المستند الالزامي لا يدخل السجل بلا ما يجعله قابلا للتنبيه', 'مرفوض',
     $must(!$badDoc['ok'] && $badDoc['code'] === 'MANDATORY_DOC_WITHOUT_EXPIRY'), $company);

$trR = PPL::recordTraining($G, array(
    'employee_id' => $empId, 'program_code' => $TAG . '-SAFE', 'program_ar' => 'سلامة تشغيل المعدات',
    'training_kind' => 'safety', 'mandatory' => 1, 'started_at' => date('Y-m-d'),
    'completed_at' => date('Y-m-d'), 'certificate_ref' => $TAG . '-CERT',
    'valid_until' => date('Y-m-d', strtotime('+2 years')), 'state' => 'completed', 'src_ref' => $TAG,
));
$trN = (int) $G->count('hr_training_record', array('where' => array('employee_id' => $empId, 'state' => 'completed')));
$log('التأهيل', 'تدريب سلامة الزامي مكتمل بصلاحيته', 'hr_training_record', 'Employees/hr_training.php',
     'برنامج مكتمل واحد بتاريخ صلاحيته', 'برامج مكتملة=' . $trN,
     'سطح التدريب يقرا صلاحية شهادة السلامة فينبه قبل انتهائها', 'completed',
     $must($trR['ok'] && $trN === 1), $company);

$badTr = PPL::recordTraining($G, array(
    'employee_id' => $empId, 'program_code' => $TAG . '-COMP', 'program_ar' => 'امتثال',
    'training_kind' => 'compliance', 'mandatory' => 1, 'completed_at' => date('Y-m-d'),
    'certificate_ref' => $TAG . '-C2', 'state' => 'completed', 'src_ref' => $TAG,
));
$log('التأهيل', 'تدريب الزامي مكتمل بلا صلاحية يرد', 'hr_training_record', 'PeopleCycleService',
     'رمز الرد MANDATORY_TRAINING_WITHOUT_EXPIRY', 'الرمز=' . $badTr['code'],
     'شهادة بلا انتهاء تجعل التدريب الالزامي غير قابل للمتابعة', 'مرفوض',
     $must(!$badTr['ok'] && $badTr['code'] === 'MANDATORY_TRAINING_WITHOUT_EXPIRY'), $company);

foreach (array(array('CUSTODY', 'تسليم العهدة'), array('SAFETY_BRIEF', 'تعريف السلامة'),
               array('ACCESS', 'فتح صلاحيات النظام')) as $it) {
    PPL::addOnboardingItem($G, array('employee_id' => $empId, 'item_code' => $TAG . '-' . $it[0],
        'item_ar' => $it[1], 'mandatory' => 1, 'src_ref' => $TAG));
}
$early = PPL::completeOnboarding($G, $empId, $A_HRMGR);
$openItems = (int) $G->count('hr_onboarding_item',
    array('where' => array('employee_id' => $empId, 'mandatory' => 1, 'state' => 'pending')));
$log('التأهيل', 'مباشرة كاملة ببنود الزامية معلقة ترد', 'hr_onboarding_item', 'PeopleCycleService',
     'رمز الرد ONBOARDING_INCOMPLETE', 'الرمز=' . $early['code'] . ' · معلق=' . $openItems,
     'المباشرة لا تعلن كاملة والعهدة لم تسلم', 'pending',
     $must(!$early['ok'] && $early['code'] === 'ONBOARDING_INCOMPLETE' && $openItems === 3), $company);

$items = $G->select('hr_onboarding_item', array('where' => array('employee_id' => $empId)));
$doneN = 0;
foreach ($items as $ix) {
    if (strpos((string) $ix['item_code'], 'ACCESS') !== false) {
        $r = PPL::settleOnboardingItem($G, (int) $ix['id'], 'waived',
            array('waiver_doc_ref' => $TAG . '-WAIVER'), $A_HRMGR);
    } else {
        $r = PPL::settleOnboardingItem($G, (int) $ix['id'], 'done',
            array('custody_doc_ref' => $TAG . '-CUST'), $A_HRMGR);
    }
    if ($r['ok']) { $doneN++; }
}
$noDoc = PPL::settleOnboardingItem($G, (int) $items[0]['id'], 'waived', array(), $A_HRMGR);
$log('التأهيل', 'استثناء بند تهيئة بلا مستند يرد', 'hr_onboarding_item', 'PeopleCycleService',
     'رمز الرد WAIVER_WITHOUT_DOCUMENT', 'الرمز=' . $noDoc['code'],
     'الاستثناء لا يمر بلا توثيق فلا تصير القائمة شكلا بلا اثر', 'مرفوض',
     $must(!$noDoc['ok'] && $noDoc['code'] === 'WAIVER_WITHOUT_DOCUMENT'), $company);

$onb = PPL::completeOnboarding($G, $empId, $A_HRMGR);
$openAfter = (int) $G->count('hr_onboarding_item',
    array('where' => array('employee_id' => $empId, 'mandatory' => 1, 'state' => 'pending')));
$log('التأهيل', 'اكتمال التهيئة يفتح المباشرة', 'hr_onboarding_item', 'Workforce/payroll_runs.php',
     'صفر بند الزامي معلق', 'معلق بعد=' . $openAfter . ' · منجز=' . $doneN,
     'الموظف يصير مؤهلا للادراج في المسير فلا يدرج من لم تكتمل مباشرته', 'onboarded',
     $must($onb['ok'] && $openAfter === 0 && $doneN === 3), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ③ · الإسنادُ لموقعٍ — حركةٌ وظيفيّةٌ بموجبِها واعتمادِها
   ══════════════════════════════════════════════════════════════════════════ */
$posId = (int) $one("SELECT id FROM positions WHERE company_id = $company ORDER BY id LIMIT 1");
if ($posId <= 0) { $posId = 1; }
$mvR = PPL::requestMovement($G, array(
    'employee_id' => $empId, 'movement_kind' => 'transfer', 'to_position_id' => $posId,
    'effective_date' => date('Y-m-d'), 'doc_ref' => $TAG . '-MOVE',
    'note' => 'اسناد العامل لموقع تشغيل', 'src_ref' => $TAG,
), $A_HRSPEC);
$log('الإسناد', 'طلب حركة وظيفية بموجبها', 'hr_job_movement', 'Employees/hr_job_movements.php',
     'حركة واحدة بحالة مرفوعة', 'movement_id=' . (isset($mvR['movement_id']) ? $mvR['movement_id'] : 0),
     'سطح الحركات يقرا الطلب بمرجع قراره', 'submitted', $must($mvR['ok']), $company);

$selfApp = PPL::approveMovement($G, isset($mvR['movement_id']) ? $mvR['movement_id'] : 0, $A_HRSPEC);
$log('الإسناد', 'من طلب الحركة لا يعتمدها', 'hr_job_movement', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT', 'الرمز=' . $selfApp['code'],
     'الاعتماد لا يقع بيد الطالب فيبقى للحركة شاهدان', 'مرفوض',
     $must(!$selfApp['ok'] && $selfApp['code'] === 'SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT'), $company);

$okApp = PPL::approveMovement($G, isset($mvR['movement_id']) ? $mvR['movement_id'] : 0, $A_AUTH);
$appN = (int) $G->count('hr_job_movement', array('where' => array('employee_id' => $empId, 'state' => 'approved')));
$log('الإسناد', 'اعتماد الحركة من غير طالبها', 'hr_job_movement', 'Employees/hr_job_movements.php',
     'حركة معتمدة واحدة', 'حركات معتمدة=' . $appN,
     'المنصب الجديد يقرا في سجل الحركات فيتغير موضع الموظف بقرار لا صامتا', 'approved',
     $must($okApp['ok'] && $appN === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ④ · الحضورُ الفعليُّ والأداء
   ══════════════════════════════════════════════════════════════════════════ */
$attN = 0;
for ($d = 0; $d < 3; $d++) {
    $day = date('Y-m-d', strtotime('-' . $d . ' day'));
    try {
        $G->insert('attendance_days', array(
            'person_id' => $empId, 'att_date' => $day, 'status_code' => 'P',
            'reference_doc' => $TAG . '-ATT', 'classified_by' => $A_HRSPEC,
            'classified_at' => date('Y-m-d H:i:s'),
        ));
        $attN++;
    } catch (\Throwable $t) { /* يوم مكرر يرد بمفتاحه — وهو مقصود */ }
}
$attRows = (int) $G->count('attendance_days', array('where' => array('person_id' => $empId)));
$log('الحضور', 'حضور فعلي برمز واحد لليوم', 'attendance_days', 'Operations/attendance.php',
     'ثلاثة ايام حضور بلا تكرار', 'ايام مسجلة=' . $attRows,
     'شاشة الحضور تقرا ايام العامل فيصير له اساس اجر', 'P',
     $must($attRows === 3), $company);

$selfRev = PPL::finalizeReview($G, array(
    'employee_id' => $empId, 'cycle_code' => $TAG . '-CY', 'criteria_ref' => 'HR-CRIT-01',
    'score' => '85', 'src_ref' => $TAG,
), $empId);
$log('الأداء', 'لا يقيم احد نفسه', 'hr_performance_review', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_REVIEW_SELF', 'الرمز=' . $selfRev['code'],
     'التقييم راي غيره لا رايه في نفسه', 'مرفوض',
     $must(!$selfRev['ok'] && $selfRev['code'] === 'SAME_ACTOR_REVIEW_SELF'), $company);

$revR = PPL::finalizeReview($G, array(
    'employee_id' => $empId, 'cycle_code' => $TAG . '-CY', 'criteria_ref' => 'HR-CRIT-01',
    'score' => '85', 'src_ref' => $TAG,
), $A_HRMGR);
$revN = (int) $G->count('hr_performance_review',
    array('where' => array('employee_id' => $empId, 'state' => 'finalized')));
$log('الأداء', 'تقييم وظيفي نهائي بمعاييره', 'hr_performance_review', 'Employees/hr_performance.php',
     'تقييم نهائي واحد بدرجته', 'تقييمات نهائية=' . $revN,
     'سطح التقييم يقرا درجة الدورة بمرجع معاييرها', 'finalized',
     $must($revR['ok'] && $revN === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤ · بلاغٌ يخصُّه يُنشئه مُبلِّغٌ غيرُه — الأطرافُ الأربعةُ متمايزة
   ══════════════════════════════════════════════════════════════════════════ */
$tkId = (int) $G->insert('tickets', array(
    'ticket_no' => $TAG . '-TK1', 'ticket_type_id' => $ticketType, 'stage' => 'new',
    'head_state' => 'open', 'ticket_nature' => 'incident', 'priority' => 'high',
    'confidentiality' => 'normal', 'business_impact' => 'production_critical',
    'production_critical' => 1, 'call_date' => date('Y-m-d'),
    'reporting_person' => $TAG . ' مبلغ', 'is_anonymous' => 0,
    'complaint' => 'واقعة تخص العامل في الموقع', 'owner_role_id' => $ownerRole,
    'is_parent' => 0, 'ticket_role' => 'standalone', 'is_recurring' => 0, 'escalation_level' => 0,
));
$openR = PPL::openTicket($G, $tkId,
    array('kind' => 'PERSON', 'id' => $A_REPORTER, 'dept' => 'DEP-12',
          'why' => 'مبلغ من الموقع', 'src_ref' => $TAG),
    array('kind' => 'PERSON', 'id' => $empId, 'dept' => 'DEP-07', 'subject_type' => $subjType,
          'why' => 'محل البلاغ العامل نفسه', 'src_ref' => $TAG),
    $A_CENTER);
$partyN = (int) $G->count('tkt_party', array('where' => array('ticket_id' => $tkId)));
$log('البلاغ', 'مبلغ ومحل بلاغ متمايزان بمفتاحيهما', 'tkt_party', 'Tickets/tkt_parties.php',
     'طرفان مسجلان بمفتاحين مختلفين', 'اطراف=' . $partyN,
     'سطح الاطراف يقرا من بلغ منفصلا عن عم بلغ', 'REPORTER+SUBJECT',
     $must($openR['ok'] && $partyN === 2), $company);

$selfParty = PPL::recordParty($G, $tkId, 'TICKET_OWNER',
    array('kind' => 'PERSON', 'id' => $A_REPORTER, 'dept' => 'DEP-10', 'src_ref' => $TAG), $A_CENTER);
$log('البلاغ', 'فاعل واحد لا يشغل دورين في بلاغ واحد', 'tkt_party', 'PeopleCycleService',
     'رمز الرد MERGED_PARTY_ACTOR', 'الرمز=' . $selfParty['code'],
     'الدمج يرد في القاعدة بمفتاحها لا بالنية', 'مرفوض',
     $must(!$selfParty['ok'] && $selfParty['code'] === 'MERGED_PARTY_ACTOR'), $company);

$ownR = PPL::takeOwnership($G, $tkId, $A_CENTER, $A_CENTER);
$ownDept = (string) $one("SELECT actor_dept FROM tkt_party WHERE ticket_id = $tkId
                           AND party_role = 'TICKET_OWNER' LIMIT 1");
$log('البلاغ', 'مالك دورة التذكرة في مركز البلاغات', 'tkt_party', 'Tickets/tkt_parties.php',
     'ادارة مالك التذكرة DEP-10', 'الادارة=' . $ownDept,
     'المركز يملك الدورة فيقرا البلاغ في لوحته', 'TICKET_OWNER',
     $must($ownR['ok'] && $ownDept === 'DEP-10'), $company);

$badOwn = PPL::recordParty($G, $tkId, 'RESOLUTION_OWNER',
    array('kind' => 'PERSON', 'id' => $A_SOLVER, 'dept' => 'DEP-10', 'src_ref' => $TAG), $A_CENTER);
$log('البلاغ', 'مالك الحل لا يكون ادارة البلاغات', 'tkt_party', 'PeopleCycleService',
     'رمز الرد RESOLUTION_BY_TICKET_CENTER', 'الرمز=' . $badOwn['code'],
     'المركز لا يملك تنفيذ الحل فلا يسجل مالكا له', 'مرفوض',
     $must(!$badOwn['ok'] && $badOwn['code'] === 'RESOLUTION_BY_TICKET_CENTER'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑥ · التوجيهُ والإسنادُ لمالكِ الحلِّ في إدارتِه المختصّة
   ══════════════════════════════════════════════════════════════════════════ */
$badRoute = PPL::route($G, $tkId, array('to_dept' => 'DEP-10', 'route_kind' => 'AUTO',
    'rule_ref' => 'SLA-MATRIX-01', 'src_ref' => $TAG), $A_CENTER);
$log('التوجيه', 'لا توجيه لادارة البلاغات كمالك حل', 'tkt_routing_history', 'PeopleCycleService',
     'رمز الرد ROUTED_TO_TICKET_CENTER', 'الرمز=' . $badRoute['code'],
     'الوجهة ادارة مختصة لا المركز نفسه', 'مرفوض',
     $must(!$badRoute['ok'] && $badRoute['code'] === 'ROUTED_TO_TICKET_CENTER'), $company);

$noRule = PPL::route($G, $tkId, array('to_dept' => 'DEP-14', 'route_kind' => 'AUTO',
    'src_ref' => $TAG), $A_CENTER);
$log('التوجيه', 'توجيه الي بلا قاعدة مرجعية يرد', 'tkt_routing_history', 'PeopleCycleService',
     'رمز الرد AUTO_ROUTE_WITHOUT_RULE', 'الرمز=' . $noRule['code'],
     'التوجيه الالي بقاعدته لا باجتهاد لحظته', 'مرفوض',
     $must(!$noRule['ok'] && $noRule['code'] === 'AUTO_ROUTE_WITHOUT_RULE'), $company);

$rtR = PPL::route($G, $tkId, array('to_dept' => 'DEP-14', 'route_kind' => 'AUTO',
    'rule_ref' => 'SLA-MATRIX-01', 'from_dept' => 'DEP-10', 'src_ref' => $TAG), $A_CENTER);
$rtN = (int) $G->count('tkt_routing_history', array('where' => array('ticket_id' => $tkId)));
$log('التوجيه', 'توجيه الي لادارة مختصة بقاعدته', 'tkt_routing_history', 'Tickets/tkt_routing.php',
     'واقعة توجيه واحدة الى غير المركز', 'وقائع توجيه=' . $rtN,
     'سطح التوجيه يقرا وجهة البلاغ فيفتح الاسناد فيها', 'AUTO',
     $must($rtR['ok'] && $rtN === 1), $company);

$noReason = PPL::route($G, $tkId, array('to_dept' => 'DEP-15', 'route_kind' => 'CENTER_CORRECTION',
    'from_dept' => 'DEP-14', 'src_ref' => $TAG), $A_CENTER);
$log('التوجيه', 'تصحيح توجيه بلا سبب مكتوب يرد', 'tkt_routing_history', 'PeopleCycleService',
     'رمز الرد ROUTE_CORRECTION_WITHOUT_REASON', 'الرمز=' . $noReason['code'],
     'الاحالة اليدوية موثقة بسببها لا صامتة', 'مرفوض',
     $must(!$noReason['ok'] && $noReason['code'] === 'ROUTE_CORRECTION_WITHOUT_REASON'), $company);

$badAssign = PPL::assign($G, $tkId, array('to_person_id' => $A_CENTER, 'to_dept' => 'DEP-10',
    'reason' => 'اسناد داخل المركز', 'src_ref' => $TAG), $A_CENTER);
$log('الإسناد', 'لا اسناد معالجة لادارة البلاغات', 'tkt_assignment_history', 'PeopleCycleService',
     'رمز الرد ASSIGN_TO_TICKET_CENTER', 'الرمز=' . $badAssign['code'],
     'المركز يوجه ولا يعالج', 'مرفوض',
     $must(!$badAssign['ok'] && $badAssign['code'] === 'ASSIGN_TO_TICKET_CENTER'), $company);

$asR = PPL::assign($G, $tkId, array('to_person_id' => $A_SOLVER, 'to_dept' => 'DEP-14',
    'reason' => 'المكلف بصيانة المعدة في موقع العامل', 'src_ref' => $TAG), $A_CENTER);
$party4 = (int) $G->count('tkt_party', array('where' => array('ticket_id' => $tkId)));
$distinctActors = (int) $one("SELECT COUNT(DISTINCT actor_id) FROM tkt_party WHERE ticket_id = $tkId");
$log('الإسناد', 'الاسناد يثبت الطرف الرابع', 'tkt_party', 'Tickets/tkt_parties.php',
     'اربعة اطراف باربعة مفاتيح متمايزة', 'اطراف=' . $party4 . ' · مفاتيح متمايزة=' . $distinctActors,
     'البلاغ يقرا باربعة اشخاص متمايزين في السجل', 'RESOLUTION_OWNER',
     $must($asR['ok'] && $party4 === 4 && $distinctActors === 4), $company);

$ackWrong = PPL::acknowledge($G, isset($asR['assignment_id']) ? $asR['assignment_id'] : 0, $A_CENTER);
$log('الإسناد', 'الاستلام من المكلف نفسه لا من مسنده', 'tkt_assignment_history', 'PeopleCycleService',
     'رمز الرد ACKNOWLEDGE_BY_OTHER', 'الرمز=' . $ackWrong['code'],
     'وقت الاستلام يثبت ان المكلف علم لا ان المركز اعلن', 'مرفوض',
     $must(!$ackWrong['ok'] && $ackWrong['code'] === 'ACKNOWLEDGE_BY_OTHER'), $company);

PPL::acknowledge($G, isset($asR['assignment_id']) ? $asR['assignment_id'] : 0, $A_SOLVER);
$ackN = (int) $one("SELECT COUNT(*) FROM tkt_assignment_history
                     WHERE ticket_id = $tkId AND received_at IS NOT NULL");
$log('الإسناد', 'لا مكلف بلا وقت استلام', 'tkt_assignment_history', 'Tickets/tkt_assignment.php',
     'اسناد واحد بوقت استلام', 'اسنادات مستلمة=' . $ackN,
     'سطح الاسناد يقرا مهلة الاستجابة من وقت الاستلام لا من وقت الاسناد', 'received',
     $must($ackN === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑦ · التصعيدُ عند التجاوز
   ══════════════════════════════════════════════════════════════════════════ */
/* ⚠ **والابنُ يُعزَل بأبيه، فأبٌ بلا كيانٍ لا يملكه أحد**: `ticket_workstreams`
     عمودُ كيانِه يقبل العدمَ، وصفٌّ يُكتب بلا كيانٍ يجعل كلَّ أبنائِه مرفوضين
     في البوّابةِ برسالةِ «أبٌ غيرُ مملوك» — والرحلةُ تكتبه صراحةً. */
$wsId = (int) $G->insert('ticket_workstreams', array(
    'tk_id' => $tkId, 'company_id' => $company, 'workstream_type' => $TAG . '-WS', 'seq_no' => 1,
    'assignee_person_id' => $A_SOLVER, 'mandatory' => 1, 'state' => 'in_progress',
    'activation_state' => 'opened', 'received_at' => date('Y-m-d H:i:s'),
));
$maxLevel = (int) PPL::threshold('TKT_ESCALATION_MAX_LEVEL');
$overEsc = PPL::escalate($G, $wsId, $maxLevel + 1, $A_HRMGR, $A_CENTER);
$log('التصعيد', 'مستوى فوق سقف السلم المسجل يرد', 'ticket_escalations', 'PeopleCycleService',
     'رمز الرد ESCALATION_LEVEL_OUT_OF_LADDER', 'الرمز=' . $overEsc['code'] . ' · السقف=' . $maxLevel,
     'السقف من السجل لا من الشيفرة فتغييره قرار ادارة', 'مرفوض',
     $must(!$overEsc['ok'] && $overEsc['code'] === 'ESCALATION_LEVEL_OUT_OF_LADDER'), $company);

$escR = PPL::escalate($G, $wsId, 2, $A_HRMGR, $A_CENTER);
$escN = (int) $one("SELECT COUNT(*) FROM ticket_escalations WHERE ws_id = $wsId");
$log('التصعيد', 'تصعيد بمستواه عند تجاوز المهلة', 'ticket_escalations', 'Tickets/tkt_escalation.php',
     'واقعة تصعيد واحدة بمستوى ضمن السلم', 'وقائع تصعيد=' . $escN,
     'سطح التصعيد يقرا مستوى البلاغ فيراه مدير الادارة المعالجة', '2',
     $must($escR['ok'] && $escN === 1), $company);

$comR = PPL::communicate($G, $tkId, array('channel' => 'phone',
    'note' => $TAG . ' ابلاغ المبلغ بتصعيد بلاغه'), $A_CENTER);
$comN = (int) $one("SELECT COUNT(*) FROM ticket_communications WHERE tk_id = $tkId");
$log('التواصل', 'كل تواصل سطر بقناته ووقته', 'ticket_communications', 'Tickets/tkt_communications.php',
     'واقعة تواصل واحدة بقناتها', 'وقائع تواصل=' . $comN,
     'سطح التواصل يقرا ما بلغ به المبلغ فلا يبقى دور المركز شفهيا', 'phone',
     $must($comR['ok'] && $comN === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑧ · المعالجةُ عند مالكِها ثمَّ التحقّقُ ثمَّ الإغلاق
   ══════════════════════════════════════════════════════════════════════════ */
$earlyRes = PPL::resolve($G, $tkId, array('resolved_dept' => 'DEP-14',
    'priority_code' => 'high', 'src_ref' => $TAG), $A_SOLVER);
$log('المعالجة', 'لا معالجة تعلن بلا اجراء مسجل', 'tkt_verification', 'PeopleCycleService',
     'رمز الرد RESOLVE_WITHOUT_ACTION', 'الرمز=' . $earlyRes['code'],
     'اعلان المعالجة يسنده عمل وقع لا نية', 'مرفوض',
     $must(!$earlyRes['ok'] && $earlyRes['code'] === 'RESOLVE_WITHOUT_ACTION'), $company);

$crpAct = PPL::recordAction($G, $tkId, array('executor_dept' => 'DEP-10',
    'action_ar' => 'معالجة من المركز', 'dept_screen_ref' => 'Tickets/tickets_list.php',
    'src_ref' => $TAG), $A_CENTER);
$log('المعالجة', 'اجراء معالجة من المركز يرد', 'tkt_resolution_action', 'PeopleCycleService',
     'رمز الرد RESOLUTION_BY_TICKET_CENTER', 'الرمز=' . $crpAct['code'],
     'المعالجة عند مالكها في ادارته المختصة', 'مرفوض',
     $must(!$crpAct['ok'] && $crpAct['code'] === 'RESOLUTION_BY_TICKET_CENTER'), $company);

$noRef = PPL::recordAction($G, $tkId, array('executor_dept' => 'DEP-14',
    'action_ar' => 'اصلاح', 'src_ref' => $TAG), $A_SOLVER);
$log('المعالجة', 'اجراء بلا مرجع في شاشة ادارته يرد', 'tkt_resolution_action', 'PeopleCycleService',
     'رمز الرد ACTION_WITHOUT_DEPT_REF', 'الرمز=' . $noRef['code'],
     'الاجراء اثر في شاشة ادارته لا سطر في شاشة البلاغات وحدها', 'مرفوض',
     $must(!$noRef['ok'] && $noRef['code'] === 'ACTION_WITHOUT_DEPT_REF'), $company);

$actR = PPL::recordAction($G, $tkId, array('executor_dept' => 'DEP-14',
    'action_ar' => 'استبدال قطعة الفرامل وفحص الجاهزية',
    'dept_screen_ref' => 'Maintenance/work_orders.php', 'dept_doc_ref' => $TAG . '-WO',
    'src_ref' => $TAG), $A_SOLVER);
$actN = (int) $G->count('tkt_resolution_action', array('where' => array('ticket_id' => $tkId)));
$log('المعالجة', 'اجراء معالجة بمرجعه في شاشة ادارته', 'tkt_resolution_action',
     'Tickets/tkt_resolution_actions.php',
     'اجراء واحد بمرجع شاشة ادارته', 'اجراءات=' . $actN,
     'اعلان المعالجة يصير ممكنا فلا يقفل بلاغ بلا عمل وقع', 'DEP-14',
     $must($actR['ok'] && $actN === 1), $company);

$resR = PPL::resolve($G, $tkId, array('resolved_dept' => 'DEP-14',
    'priority_code' => 'critical', 'note' => 'اكتملت المعالجة', 'src_ref' => $TAG), $A_SOLVER);
$winCrit = (int) PPL::threshold('TKT_VERIFY_WINDOW_CRITICAL_H');
$winRow = (int) $one("SELECT window_hours FROM tkt_verification WHERE ticket_id = $tkId
                       ORDER BY cycle_no DESC LIMIT 1");
$log('التحقق', 'نافذة التحقق من السجل بحسب الاولوية', 'tkt_verification', 'Tickets/tkt_verification.php',
     'نافذة الحرج ' . $winCrit . ' ساعة', 'المكتوب=' . $winRow,
     'المبلغ يقرا مهلة اعتراضه بزمن معلن من السجل لا من الشيفرة', 'verification',
     $must($resR['ok'] && $winRow === $winCrit && $winCrit > 0), $company);

$vid = isset($resR['verification_id']) ? (int) $resR['verification_id'] : 0;
$selfVer = PPL::verify($G, $vid, 'SPECIALIST', $A_SOLVER);
$log('التحقق', 'من عالج لا يتحقق من عمله', 'tkt_verification', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_RESOLVE_AND_VERIFY', 'الرمز=' . $selfVer['code'],
     'التحقق شهادة غيره لا شهادته على نفسه', 'مرفوض',
     $must(!$selfVer['ok'] && $selfVer['code'] === 'SAME_ACTOR_RESOLVE_AND_VERIFY'), $company);

$autoCrit = PPL::verify($G, $vid, 'AUTO_WINDOW', $A_CENTER);
$log('التحقق', 'لا اغلاق الي للبلاغ الحرج', 'tkt_verification', 'PeopleCycleService',
     'رمز الرد AUTO_CLOSE_ON_CRITICAL', 'الرمز=' . $autoCrit['code'],
     'الحرج يغلق بشخص لا بمرور الوقت - جواب DEC-OPEN-05', 'مرفوض',
     $must(!$autoCrit['ok'] && $autoCrit['code'] === 'AUTO_CLOSE_ON_CRITICAL'), $company);

$earlyClose = PPL::close($G, $vid, $A_CENTER);
$log('الإغلاق', 'لا اغلاق بلا تحقق', 'tkt_verification', 'PeopleCycleService',
     'رمز الرد CLOSE_WITHOUT_VERIFICATION', 'الرمز=' . $earlyClose['code'],
     'الاغلاق يعقب التحقق ولا يبدله', 'مرفوض',
     $must(!$earlyClose['ok'] && $earlyClose['code'] === 'CLOSE_WITHOUT_VERIFICATION'), $company);

$verR = PPL::verify($G, $vid, 'REPORTER', $A_REPORTER);
$verState = (string) $one("SELECT state FROM tkt_verification WHERE id = $vid");
$log('التحقق', 'المبلغ يتحقق من معالجة بلاغه', 'tkt_verification', 'Tickets/tkt_verification.php',
     'الحالة verified وصفة المتحقق REPORTER', 'الحالة=' . $verState,
     'الاغلاق يصير ممكنا وقد شهد المبلغ على الحل', 'verified',
     $must($verR['ok'] && $verState === 'verified'), $company);

$clR = PPL::close($G, $vid, $A_CENTER);
$clState = (string) $one("SELECT state FROM tkt_verification WHERE id = $vid");
$log('الإغلاق', 'اغلاق البلاغ بعد تحققه', 'tkt_verification', 'Tickets/tkt_verification.php',
     'الحالة closed', 'الحالة=' . $clState,
     'اعادة الفتح تصير ممكنة على دورة مغلقة وحدها', 'closed',
     $must($clR['ok'] && $clState === 'closed'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑨ · إعادةُ الفتحِ — تعود لمسارِ المعالجةِ لا لبدايتِه
   ══════════════════════════════════════════════════════════════════════════ */
$reNoWhy = PPL::reopen($G, $tkId, array('reopen_reason' => 'REPORTER_OBJECTION',
    'back_to_dept' => 'DEP-14', 'src_ref' => $TAG), $A_REPORTER);
$log('إعادة الفتح', 'اعادة فتح بلا سبب مكتوب ترد', 'tkt_reopen', 'PeopleCycleService',
     'رمز الرد REOPEN_WITHOUT_REASON', 'الرمز=' . $reNoWhy['code'],
     'الاعتراض بسببه لا بضغطة زر', 'مرفوض',
     $must(!$reNoWhy['ok'] && $reNoWhy['code'] === 'REOPEN_WITHOUT_REASON'), $company);

$reCenter = PPL::reopen($G, $tkId, array('reopen_reason' => 'REPORTER_OBJECTION',
    'note' => $TAG . ' العطل تكرر', 'back_to_dept' => 'DEP-10', 'src_ref' => $TAG), $A_REPORTER);
$log('إعادة الفتح', 'العودة لمسار المعالجة لا لمركز البلاغات', 'tkt_reopen', 'PeopleCycleService',
     'رمز الرد REOPEN_BACK_TO_CENTER', 'الرمز=' . $reCenter['code'],
     'اعادة الفتح تعود بالبلاغ الى ادارة حله لا الى نقطة تسجيله', 'مرفوض',
     $must(!$reCenter['ok'] && $reCenter['code'] === 'REOPEN_BACK_TO_CENTER'), $company);

$reR = PPL::reopen($G, $tkId, array('reopen_reason' => 'REPORTER_OBJECTION',
    'note' => $TAG . ' العطل تكرر بعد الاصلاح', 'back_to_dept' => 'DEP-14',
    'src_ref' => $TAG), $A_REPORTER);
$oldState = (string) $one("SELECT state FROM tkt_verification WHERE id = $vid");
$log('إعادة الفتح', 'اعادة فتح باعتراض المبلغ', 'tkt_reopen', 'Tickets/tkt_reopen.php',
     'الدورة السابقة تصير reopened', 'حالة الدورة السابقة=' . $oldState,
     'سطح اعادة الفتح يقرا اعتراض المبلغ ويعيد البلاغ لادارة حله', 'reopened',
     $must($reR['ok'] && $oldState === 'reopened'), $company);

PPL::recordAction($G, $tkId, array('executor_dept' => 'DEP-14',
    'action_ar' => 'اعادة اصلاح جذري بعد تكرار العطل',
    'dept_screen_ref' => 'Maintenance/work_orders.php', 'src_ref' => $TAG), $A_SOLVER);
$res2 = PPL::resolve($G, $tkId, array('resolved_dept' => 'DEP-14',
    'priority_code' => 'normal', 'src_ref' => $TAG), $A_SOLVER);
$cycles = (int) $G->count('tkt_verification', array('where' => array('ticket_id' => $tkId)));
$winNorm = (int) PPL::threshold('TKT_VERIFY_WINDOW_NORMAL_H');
$win2 = (int) $one("SELECT window_hours FROM tkt_verification WHERE ticket_id = $tkId
                     ORDER BY cycle_no DESC LIMIT 1");
$log('إعادة الفتح', 'دورة تحقق ثانية برقمها لا دورة تدهس سابقتها', 'tkt_verification',
     'Tickets/tkt_verification.php',
     'دورتان بارقامهما ونافذة العادي ' . $winNorm, 'دورات=' . $cycles . ' · النافذة=' . $win2,
     'السطح يقرا الدورتين منفصلتين فيظهر ان البلاغ اعيد فتحه مرة', 'verification',
     $must($res2['ok'] && $cycles === 2 && $win2 === $winNorm), $company);

$vid2 = isset($res2['verification_id']) ? (int) $res2['verification_id'] : 0;
PPL::verify($G, $vid2, 'SPECIALIST', $A_VERIFIER);
PPL::close($G, $vid2, $A_CENTER);
$closedN = (int) $G->count('tkt_verification', array('where' => array('ticket_id' => $tkId, 'state' => 'closed')));
$log('الإغلاق', 'اغلاق الدورة الثانية بتحقق مختص', 'tkt_verification', 'Tickets/tkt_verification.php',
     'دورة مغلقة واحدة والاخرى معادة الفتح', 'مغلقة=' . $closedN,
     'البلاغ يقرا مغلقا بدورته الثانية وسجل دورته الاولى باق', 'closed',
     $must($closedN === 1), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑩ · القضيّةُ التأديبيّةُ والخصمُ — ثلاثُ أيدٍ لا يدٌ واحدة
   ══════════════════════════════════════════════════════════════════════════ */
$selfCase = PPL::openDisciplinaryCase($G, array('case_no' => $TAG . '-C0',
    'employee_id' => $empId, 'incident_ar' => 'واقعة', 'src_ref' => $TAG), $empId);
$log('القضية', 'لا يبلغ الموظف عن نفسه', 'hr_disciplinary_case', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_SUBJECT_AND_REPORTER', 'الرمز=' . $selfCase['code'],
     'المبلغ ومحل البلاغ التاديبي شخصان', 'مرفوض',
     $must(!$selfCase['ok'] && $selfCase['code'] === 'SAME_ACTOR_SUBJECT_AND_REPORTER'), $company);

$caseR = PPL::openDisciplinaryCase($G, array('case_no' => $TAG . '-CASE',
    'employee_id' => $empId, 'incident_at' => date('Y-m-d H:i:s'),
    'incident_ar' => 'مخالفة تعليمات السلامة في الموقع',
    'reporter_role' => 'مشرف الموقع', 'src_ref' => $TAG), $A_REPORTER);
$caseId = isset($caseR['case_id']) ? (int) $caseR['case_id'] : 0;
$stageRows = (int) $one("SELECT COUNT(*) FROM hr_disciplinary_stage WHERE case_id = $caseId");
$log('القضية', 'فتح قضية بواقعتها', 'hr_disciplinary_case', 'Employees/hr_disciplinary.php',
     'مرحلة واقعة واحدة مسجلة', 'مراحل=' . $stageRows,
     'سطح القضايا يقرا الواقعة بمبلغها قبل ان يقرا قرارها', 'incident',
     $must($caseR['ok'] && $stageRows === 1), $company);

$invSelf = PPL::assignInvestigator($G, $caseId, $A_REPORTER,
    array('investigation_owner_dept' => 'DEP-07', 'src_ref' => $TAG), $A_HRMGR);
$log('القضية', 'من بلغ لا يحقق', 'hr_disciplinary_case', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_REPORT_AND_INVESTIGATE', 'الرمز=' . $invSelf['code'],
     'التحقيق يد ثانية غير يد التبليغ', 'مرفوض',
     $must(!$invSelf['ok'] && $invSelf['code'] === 'SAME_ACTOR_REPORT_AND_INVESTIGATE'), $company);

$iafNoDoc = PPL::assignInvestigator($G, $caseId, $A_INVEST,
    array('investigation_owner_dept' => 'IAF', 'src_ref' => $TAG), $A_HRMGR);
$log('القضية', 'تكليف المراجعة الداخلية بلا مستند يرد', 'hr_disciplinary_case', 'PeopleCycleService',
     'رمز الرد IAF_WITHOUT_ASSIGNMENT_DOC', 'الرمز=' . $iafNoDoc['code'],
     'المراجعة الداخلية بتكليف موثق لا باختصاص اصيل - جواب DEC-OPEN-16', 'مرفوض',
     $must(!$iafNoDoc['ok'] && $iafNoDoc['code'] === 'IAF_WITHOUT_ASSIGNMENT_DOC'), $company);

$invR = PPL::assignInvestigator($G, $caseId, $A_INVEST,
    array('investigation_owner_dept' => 'DEP-07', 'investigator_role' => 'اخصائي موارد',
          'note' => 'تكليف بالتحقيق', 'src_ref' => $TAG), $A_HRMGR);
$invDept = (string) $one("SELECT investigation_owner_dept FROM hr_disciplinary_case WHERE id = $caseId");
$log('القضية', 'تكليف محقق من ادارة مالكة معلنة', 'hr_disciplinary_case', 'Employees/hr_disciplinary.php',
     'ادارة التحقيق DEP-07', 'الادارة=' . $invDept,
     'سطح القضايا يقرا مالك التحقيق فيعرف من يسال عنه', 'investigation',
     $must($invR['ok'] && $invDept === 'DEP-07'), $company);

$decSelf = PPL::decideCase($G, $caseId, array('decision_kind' => 'deduction',
    'decision_ref' => $TAG . '-DEC', 'src_ref' => $TAG), $A_INVEST);
$log('القضية', 'من حقق لا يقرر', 'hr_disciplinary_case', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_INVESTIGATE_AND_DECIDE', 'الرمز=' . $decSelf['code'],
     'القرار يد ثالثة غير يد التحقيق', 'مرفوض',
     $must(!$decSelf['ok'] && $decSelf['code'] === 'SAME_ACTOR_INVESTIGATE_AND_DECIDE'), $company);

$runId = (int) $one("SELECT id FROM payroll_runs WHERE company_id = $company ORDER BY id DESC LIMIT 1");
$earlyDed = PPL::raiseDeduction($G, array('case_id' => $caseId, 'amount' => '150',
    'run_id' => $runId, 'note' => $TAG . ' خصم مبكر'), $A_HRSPEC);
$log('الخصم', 'لا خصم بلا قرار قضية محسوم', 'payroll_deductions', 'PeopleCycleService',
     'رمز الرد DEDUCTION_WITHOUT_DECIDED_CASE', 'الرمز=' . $earlyDed['code'],
     'الخصم فرع بمرجع قراره لا حكم يكتب في شاشة المسير', 'مرفوض',
     $must(!$earlyDed['ok'] && $earlyDed['code'] === 'DEDUCTION_WITHOUT_DECIDED_CASE'), $company);

$decR = PPL::decideCase($G, $caseId, array('decision_kind' => 'deduction',
    'decision_ref' => $TAG . '-DEC', 'decider_role' => 'المخول بسقفه',
    'note' => 'قرار خصم', 'src_ref' => $TAG), $A_DECIDER);
$stageAll = (int) $one("SELECT COUNT(DISTINCT stage) FROM hr_disciplinary_stage WHERE case_id = $caseId");
$log('القضية', 'قرار تاديبي بعد ثلاث مراحل', 'hr_disciplinary_case', 'Employees/hr_disciplinary.php',
     'ثلاث مراحل متمايزة مسجلة', 'مراحل متمايزة=' . $stageAll,
     'الخصم يصير ممكنا بمرجع القرار وحده', 'decided',
     $must($decR['ok'] && $stageAll === 3), $company);

$dedR = PPL::raiseDeduction($G, array('case_id' => $caseId, 'amount' => '150',
    'run_id' => $runId, 'note' => $TAG . ' خصم بمرجع قراره'), $A_HRSPEC);
$dedRef = (string) $one("SELECT doc_ref FROM payroll_deductions
                          WHERE source_type = 'penalty' AND source_id = $caseId
                            AND note LIKE '$TAG%' ORDER BY id DESC LIMIT 1");
$log('الخصم', 'خصم مسنود بمرجع قرار قضيته', 'payroll_deductions', 'Workforce/payroll_lines.php',
     'مرجع الخصم يساوي مرجع القرار', 'المرجع=' . $dedRef,
     'سطر الخصم يظهر في اسطر المسير بمرجع قراره فيقرا الموظف سبب خصمه', 'penalty',
     $must($dedR['ok'] && $dedRef === $TAG . '-DEC'), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑪ · المزايا ثمَّ تسويةُ المستحقّات
   ══════════════════════════════════════════════════════════════════════════ */
$benNoRef = PPL::enrollBenefit($G, array('employee_id' => $empId, 'benefit_code' => $TAG . '-MED',
    'benefit_ar' => 'تامين طبي', 'employer_share' => '100', 'employee_share' => '20',
    'effective_from' => date('Y-m-d'), 'src_ref' => $TAG));
$log('المزايا', 'ميزة بلا مرجع في المسير ترد', 'hr_benefit_enrollment', 'PeopleCycleService',
     'رمز الرد BENEFIT_WITHOUT_PAYROLL_REF', 'الرمز=' . $benNoRef['code'],
     'الميزة تصب في المسير بمرجعها لا تبقى سطرا معلقا', 'مرفوض',
     $must(!$benNoRef['ok'] && $benNoRef['code'] === 'BENEFIT_WITHOUT_PAYROLL_REF'), $company);

$benR = PPL::enrollBenefit($G, array('employee_id' => $empId, 'benefit_code' => $TAG . '-MED',
    'benefit_ar' => 'تامين طبي', 'employer_share' => '100', 'employee_share' => '20',
    'effective_from' => date('Y-m-d'), 'payroll_component_ref' => 'PAY-COMP-MED',
    'src_ref' => $TAG));
$benN = (int) $G->count('hr_benefit_enrollment', array('where' => array('employee_id' => $empId, 'state' => 'active')));
$log('المزايا', 'اشتراك بحصتيه ومرجعه في المسير', 'hr_benefit_enrollment', 'Employees/hr_benefits.php',
     'اشتراك ساري واحد', 'اشتراكات سارية=' . $benN,
     'مكون المزايا يدرج في اسطر المسير بمرجعه', 'active',
     $must($benR['ok'] && $benN === 1), $company);

/* **والتصفيةُ لا تقوم بلا عقدٍ يسندها** — مفتاحٌ أجنبيٌّ يردُّ صفرًا، وهو محقّ:
   لا تصفيةَ لعلاقةٍ لم تُكتب. فالرحلةُ تُنشئ عقدَ العاملِ ثمَّ تصفّيه. */
$payModel = (int) $one("SELECT id FROM pay_models ORDER BY id LIMIT 1");
$conId = (int) $G->insert('employee_contracts', array(
    'employee_id' => $empId, 'category' => 'operator', 'relation_type' => 'direct',
    'start_date' => date('Y-m-d'), 'pay_model_id' => $payModel, 'currency' => 'SDG',
    'state' => 'active', 'signed_file_ref' => $TAG . '-CONTRACT', 'created_by' => $A_HRSPEC,
));
$log('التصفية', 'العقد المكتوب مصدر حقيقة العلاقة', 'employee_contracts', 'Employees/employee_contracts.php',
     'عقد ساري واحد للعامل', 'contract_id=' . $conId,
     'لا اجر ولا خصم ولا تصفية الا على عقد ساري', 'active',
     $must($conId > 0), $company);

$setId = (int) $G->insert('employee_final_settlements', array(
    'employee_id' => $empId, 'contract_id' => $conId, 'effective_date' => date('Y-m-d'),
    'currency' => 'SDG', 'service_years' => 1, 'dues_amount' => 1000, 'leave_amount' => 0,
    'eos_amount' => 500, 'advances_offset' => 0, 'advances_remaining' => 200,
    'net_amount' => 1500, 'state' => 'draft', 'clearance_doc' => '',
    'note' => $TAG . ' تصفية الرحلة', 'prepared_by' => $A_HRSPEC,
));
$noClear = PPL::approveSettlement($G, $setId, $A_AUTH);
$log('التصفية', 'لا تصفية قبل ابراء العهد', 'employee_final_settlements', 'PeopleCycleService',
     'رمز الرد SETTLEMENT_WITHOUT_CLEARANCE', 'الرمز=' . $noClear['code'],
     'الصرف يعقب ابراء العهدة لا يسبقه', 'مرفوض',
     $must(!$noClear['ok'] && $noClear['code'] === 'SETTLEMENT_WITHOUT_CLEARANCE'), $company);

$G->update('employee_final_settlements', array('clearance_doc' => $TAG . '-CLR'),
           array('id' => $setId));
$openAdv = PPL::approveSettlement($G, $setId, $A_AUTH);
$log('التصفية', 'سلفة قائمة تمنع التصفية', 'employee_final_settlements', 'PeopleCycleService',
     'رمز الرد SETTLEMENT_WITH_OPEN_ADVANCE', 'الرمز=' . $openAdv['code'],
     'تسوية السلف شرط لا توصية', 'مرفوض',
     $must(!$openAdv['ok'] && $openAdv['code'] === 'SETTLEMENT_WITH_OPEN_ADVANCE'), $company);

$G->update('employee_final_settlements', array('advances_remaining' => 0), array('id' => $setId));
$selfSet = PPL::approveSettlement($G, $setId, $A_HRSPEC);
$log('التصفية', 'من اعد التصفية لا يعتمدها', 'employee_final_settlements', 'PeopleCycleService',
     'رمز الرد SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT', 'الرمز=' . $selfSet['code'],
     'التصفية حكم مالي بشاهدين', 'مرفوض',
     $must(!$selfSet['ok'] && $selfSet['code'] === 'SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT'), $company);

$setR = PPL::approveSettlement($G, $setId, $A_AUTH);
$setRow = $G->selectOne('employee_final_settlements', array('where' => array('id' => $setId)));
$log('التصفية', 'اعتماد التصفية بابراء عهدة وصفر سلفة', 'employee_final_settlements',
     'Workforce/final_settlement.php',
     'مستند ابراء قائم وصفر سلفة', 'الابراء=' . (string) $setRow['clearance_doc']
     . ' · سلفة=' . (string) $setRow['advances_remaining'],
     'التصفية تصير قابلة للصرف عند الخزينة', 'approved',
     $must($setR['ok'] && (float) $setRow['advances_remaining'] === 0.0), $company);

/* ══════════════════════════════════════════════════════════════════════════
   الختامُ — الأطرافُ الأربعةُ أشخاصٌ متمايزون والمحوران صفران
   ══════════════════════════════════════════════════════════════════════════ */
$roles4 = (int) $one("SELECT COUNT(DISTINCT party_role) FROM tkt_party WHERE ticket_id = $tkId");
$act4   = (int) $one("SELECT COUNT(DISTINCT actor_id) FROM tkt_party WHERE ticket_id = $tkId");
$log('الختام', 'اربعة ادوار باربعة اشخاص متمايزين', 'tkt_party', 'Tickets/tkt_parties.php',
     'ادوار=4 ومفاتيح=4', 'ادوار=' . $roles4 . ' · مفاتيح=' . $act4,
     'الفصل الرباعي مقروء في السجل لا مكتوب في وثيقة', 'مكتمل',
     $must($roles4 === 4 && $act4 === 4), $company);

$mg = repair01_w13_party_merged($conn);
$cr = repair01_w13_resolution_owned_by_crp($conn);
$log('الختام', 'محورا المرحلة مقيسان على الحي بعد الرحلة', 'repair01_w13_parties', 'repair01_w13_gate.php',
     'طرف مدموج 0 وتنفيذ حل للبلاغات 0',
     'مدموج=' . $mg['total'] . ' · تنفيذ للمركز=' . $cr['total'],
     'الرحلة مارست الجداول فعلا ثم قيست عليها فالبناء مثبت لا مدعى', 'صفر',
     $must($mg['total'] === 0 && $cr['total'] === 0 && count($mg['unmeasured']) === 0
           && count($cr['unmeasured']) === 0), $company);

/* ══════════════════════════════════════════════════════════════════════════
   التسجيلُ والكنسُ ثمَّ الحكم
   ══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM repair01_w13_journey");
$n = 0; $passed = 0; $noEffect = 0;
foreach ($ST as $s) {
    $n++;
    if ($s[8]) { $passed++; }
    if (trim((string) $s[6]) === '') { $noEffect++; }
    $conn->query("INSERT INTO repair01_w13_journey
        (run_id,station_no,leg,station,entity,consumer,expected,measured,business_effect,
         state_after,company_id,passed,measured_at)
        VALUES ('" . $esc($RUN) . "'," . $n . ",'" . $esc($s[0]) . "','" . $esc($s[1]) . "',
                '" . $esc($s[2]) . "','" . $esc($s[3]) . "','" . $esc($s[4]) . "','" . $esc($s[5]) . "',
                '" . $esc($s[6]) . "','" . $esc($s[7]) . "'," . (int) $s[9] . "," . (int) $s[8] . ",NOW())");
    printf("  %s %-14s %s\n     متوقع: %s\n     مقيس : %s\n     أثر  : %s\n",
        $s[8] ? '✔' : '✘', $s[0], $s[1], $s[4], $s[5], $s[6]);
}

$consumers = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w13_journey WHERE run_id = '" . $esc($RUN) . "'");
$legs = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w13_journey WHERE run_id = '" . $esc($RUN) . "'");

/* **الكنسُ يُشغَّل مرّتَين** — والباقي يُقاس بعده لا يُدَّعى صفرًا */
$sweep();
$sweep();
$left = 0;
foreach (array("SELECT COUNT(*) FROM tkt_party WHERE src_ref LIKE '$TAG%'",
               "SELECT COUNT(*) FROM tkt_verification WHERE src_ref LIKE '$TAG%'",
               "SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM hr_disciplinary_case WHERE case_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM employees WHERE employee_code LIKE '$TAG%'",
               "SELECT COUNT(*) FROM rec_vacancies WHERE vacancy_no LIKE '$TAG%'",
               "SELECT COUNT(*) FROM payroll_deductions WHERE note LIKE '$TAG%'") as $q) {
    $left += (int) $one($q);
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("رحلةُ العامل: %d/%d محطّة · %d أشواط · %d مستهلكًا متمايزًا · كيانٌ واحد · بلا أثرٍ تجاريٍّ %d · أثرٌ باقٍ %d\n",
    $passed, $n, $legs, $consumers, $noEffect, $left);
$ok = ($FAIL === 0 && $passed === $n && $noEffect === 0 && $left === 0);
echo $ok ? "الحكم: عبرت ✔\n" : "الحكم: لم تعبر ✘\n";
exit($ok ? 0 : 1);
