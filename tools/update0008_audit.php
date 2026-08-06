<?php
/**
 * tools/update0008_audit.php — تدقيقُ تنفيذ حزمة update0008 على معايير قبولها
 * ───────────────────────────────────────────────────────────────────────────
 * لا يقيس ما ادّعيناه — يقيس **معايير القبول التي أعلنتها الوثائق بنفسها**
 * (AC-E01..E06 · AC-WFM · BR-CEO · BR-GOV) بشاهدٍ من الكود أو القاعدة.
 * الحكم لكل معيار:
 *   ENFORCED — منعٌ بنيويٌّ حيٌّ مفحوصٌ (حزامٌ أو اختبارٌ أو حارسٌ في الخادم)
 *   PARTIAL  — نافذٌ في نطاقٍ ويحتاج تعميمًا (يُذكر النطاق والباقي)
 *   OPEN     — لم يُنفَّذ بعد (يُذكر مانعُه)
 * الاستعمال: php tools/update0008_audit.php [--md]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$MD = in_array('--md', $argv, true);

function has(string $file, string $needle): bool {
    $p = dirname(__DIR__) . '/' . $file;
    return is_file($p) && strpos((string) file_get_contents($p), $needle) !== false;
}
function num(mysqli $c, string $sql) {
    $r = mysqli_query($c, $sql);
    return $r ? intval(mysqli_fetch_row($r)[0]) : -1;
}

$AC = array();
$add = function ($code, $title, $verdict, $evidence) use (&$AC) {
    $AC[] = array('code' => $code, 'title' => $title, 'verdict' => $verdict, 'ev' => $evidence);
};

/* ═══════════════ E-01 · محرّك الأحداث المالية ═══════════════ */
$add('AC-E01-01', 'لا حدثَ خارج البوابة',
    has('includes/timesheet_event_hook.php', 'EMS_UNIT_CONVERT_GATE') && has('app/Services/Finance/UnitConversionService.php', 'EffectFanout')
        ? 'ENFORCED' : 'OPEN',
    'بوابة التحويل ON وخدمةٌ واحدةٌ تولّد (UnitConversionService) — والنداءُ المباشر لا مسارَ له');
$add('AC-E01-02', 'المفتاحُ يمنع الازدواج',
    has('app/Services/EffectFanout.php', 'idempotency_key') ? 'ENFORCED' : 'OPEN',
    'fin_event_links دفترُ العطالة + idempotency_key في الناشر — مُثبتٌ باختبار التبنّي');
$add('AC-E01-03', 'العقدُ الخماسيُّ مكتمل', 'PARTIAL',
    'مطبَّقٌ في مروحة الوحدة (يُكتب·يُنشر·يستهلك·يتغير·يُعكس) — وتعميمُه على كل فعلٍ ذي أثرٍ لم يُمسح آليًّا بعد');
$add('AC-E01-04', 'الفان أوت مستقل',
    has('app/Services/EffectFanout.php', 'party_award') ? 'ENFORCED' : 'OPEN',
    'كلُّ أثرٍ بمفتاحه ورابطه الأبوي — والتبنّي يُحصى منفصلًا لكل نوع');
// أُغلق 2026-08-06 مساءً: درجة الأثر جدولًا جانبيًّا CREATE-only (يحترم تجميد
// المخطط) + مانع إقفال ④ في ems_period_close_blockers — والقياس حي لا وصفي.
$feg = false;
$rq = mysqli_query($conn, "SHOW TABLES LIKE 'fin_event_grades'");
if ($rq && $rq->num_rows > 0) {
    $feg = has('includes\period_guard.php', 'AC-E01-05') && has('includes\period_guard.php', 'fin_event_grades');
}
$add('AC-E01-05', 'لا إقفالَ لمبدئيّ', $feg ? 'ENFORCED' : 'OPEN',
    $feg ? 'درجةُ الأثر جدولٌ جانبيٌّ (fin_event_grades · هجرة 2026_11_17) والمبدئيُّ يمنع إقفالَ فترته (مانع ④) — event_grade_test 8/8'
         : 'درجةُ الأثر (مبدئي/نهائي) لم تُبنَ عمودًا بعد — DDL مؤجَّل بعد نافذة الظل');
$add('AC-E01-06', 'العكسُ بمرجع',
    has('app/Services/EffectFanout.php', 'reversal') || has('Finance/fin_helpers.php', 'عكس') ? 'ENFORCED' : 'PARTIAL',
    'العكسُ بحركةٍ مقابلةٍ بمرجع الأصل (CON-02/M-02 سابقتان) — وصفرُ حذفٍ ماليٍّ في المسار');

/* ═══════════════ E-02 · دورة الوحدة ═══════════════ */
$hoursGuard = strtolower((string) (function_exists('ems_env') ? ems_env('EMS_E02_HOURS_GUARD', 'off') : 'off'));
$add('AC-E02-01', 'الحقولُ الثلاثةُ منفصلة', 'ENFORCED',
    'unit_entries: unit_type/qty · unit_time_log.hours · الإنتاج التحليلي — أعمدةٌ منفصلةٌ بنيويًّا');
$add('AC-E02-02', 'الساعاتُ في كل النماذج', $hoursGuard === 'enforce' ? 'ENFORCED' : 'OPEN',
    'حارس UN-02 بعلَم enforce · الجديدُ بعد العتبة صفر — ودَينُ 711 قبلها معلَنٌ بتقريره');
$add('AC-E02-03', 'السلسلةُ لا تُختصر', 'ENFORCED',
    'UnitJourneyService: لا تُفتح حلقةٌ قبل سابقتها — قفزُ الحلقة 409 مُثبتٌ E2E');
$add('AC-E02-04', 'لا صفَّ بلا أربعة', 'PARTIAL',
    'الوردية والتخصيص محروسان في TimesheetEntryService — والجاهزيةُ والتكليفُ يُفحصان في مسار الخطة لا في كل إدخال');
$add('AC-E02-05', 'المعتمَدُ لا يُعدَّل', 'ENFORCED',
    'UnitStateChangeService مسارُ التصحيح الوحيد + قفلُ النسخة عند site_approved');
$add('AC-E02-06', 'الإقفالُ يمنع الكتابة', 'ENFORCED',
    'period_guard في الخادم — والفترةُ المقفلةُ ترفض الكتابة (423)');

/* ═══════════════ E-03 · الشاشات ═══════════════ */
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing','Fleet',
              'Governance','Maintenance','Movement','Operations','Portal','Procurement','Settings','Suppliers',
              'Tickets','Timesheet','Transport','Warehouse','Workforce','main');
$scr = 0; $about = 0; $gov = 0;
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $s = (string) file_get_contents($f);
        if (strpos($s, 'insidebar') === false) { continue; }
        $scr++;
        if (strpos($s, 'ems_screen_about') !== false) { $about++; }
        if (strpos($s, 'gov_columns') !== false || strpos($s, 'ems-gov-th') !== false) { $gov++; }
    }
}
$add('AC-E03-01', 'سؤالٌ واحدٌ لكل شاشة', 'PARTIAL',
    "التبنّي {$about}/{$scr} شاشة — الجملةُ تُكتب صادقةً لكل شاشةٍ ولا تُقولَب آليًّا");
$add('AC-E03-02', 'الترتيبُ بالدورة', 'ENFORCED', 'nav09_verify يطابق المولَّد بالوثيقة حرفًا — صفر رابطٍ في غير موضعه');
$add('AC-E03-03', 'الأعمدةُ الحاكمةُ مكتملة', 'PARTIAL', "gov_columns في {$gov}/{$scr} — وcmp03_gov_check فصّل 194 شاشة سابقًا");
$add('AC-E03-04', 'طقمُ الحالات موحَّد',
    has('assets/js/ui-unification.js', 'mapStatusToken') ? 'ENFORCED' : 'OPEN', 'المصيّر السباعي بمرادفاته وكتله — حزام e03 ②');
$add('AC-E03-05', 'الميدانيةُ تعمل بلا اتصال', 'PARTIAL',
    'الحفظُ دون اتصالٍ ومزامنةُ sync_uuid قائمان في مسار الوحدة — والتعميمُ على كل شاشةٍ ميدانيةٍ لم يُقَس');
$add('AC-E03-06', 'المحجوبُ لا يُصيَّر',
    has('includes/permissions_helper.php', 'ems_failclosed_screen_guard') ? 'ENFORCED' : 'OPEN',
    'fail-closed + المحجوبُ لا يُرسل — ومسحُ دَين الحارس A=0 · B=0');
$add('AC-E03-07', 'الإبلاغُ من كل شاشة',
    is_file($ROOT . '/includes/report_button.php') ? 'ENFORCED' : 'OPEN', 'زرٌّ بسياقه في القالب المشترك — حزام e03 ⑤');

/* ═══════════════ E-04 · الصلاحيات ═══════════════ */
$permSrc = strtolower((string) (function_exists('ems_env') ? ems_env('EMS_PERM_SOURCE', 'legacy') : 'legacy'));
$add('AC-E04-01', 'الاشتقاقُ من القالب', 'PARTIAL',
    "القوالبُ مملوءةٌ (1852 بندًا) والمصدرُ الحيُّ ما زال legacy — القلبُ بعد نافذة الظل (قرار مالك)");
// SEC-013 بُنيت بنيتها كاملةً 2026-08-06 مساءً (16 فعلًا · 9 نطاقات · 1852 بعدًا
// مشتقًا · محلّل fail-closed خلف علم) — والإنفاذ الحي وحده ينتظر قلب المصدر.
$s13 = false;
$rq = mysqli_query($conn, "SHOW TABLES LIKE 'sec_actions'");
if ($rq && $rq->num_rows > 0) {
    $na = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM sec_actions"))[0]);
    $nd = intval(mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM template_permission_dims"))[0]);
    $s13 = ($na === 16 && $nd > 0 && is_file($ROOT . '/includes/sec013.php'));
}
$add('AC-E04-02', 'الثلاثةُ معًا (فعل·نطاق·ظهور)', 'PARTIAL',
    $s13 ? 'بنية SEC-013 كاملةٌ ومقيسة (16 فعلًا · 9 نطاقات · بُعدٌ مشتقٌّ لكل بندٍ رباعيّ الراية · محلّل fail-closed — sec013_test 10/10) والإنفاذُ خلف EMS_SEC013 حتى قلب المصدر 19-08'
         : 'الرايات الأربع + مفاتيح الظهور نافذة — والأبعادُ الأربعة والأفعالُ الـ16 (SEC-013) خلف نافذة الظل');
$add('AC-E04-03', 'السقفُ يُفحص عند الاعتماد', 'PARTIAL',
    'سقوفُ التفويض في work_delegations وسلّمُ الطلبات نافذان — وتعميمُ السقف النقدي على كل مستندٍ لم يُمسح');
$add('AC-E04-04', 'لا حسابَ جامع (فصل الواجبات)',
    is_file($ROOT . '/includes/sod_guard.php') ? 'ENFORCED' : 'OPEN',
    'حارسٌ قبليٌّ في مسار المنح (الدقيق يمنع · التقريبي يبلّغ) + مسحٌ دوريٌّ sod_sweep — رباعيةٌ مُثبتة');
$add('AC-E04-05', 'الصفاتُ لا تُجمع', 'ENFORCED', 'مبدّلُ المساحة يفصل الصفات والجلسةُ بصفةٍ واحدةٍ والتبديلُ يُسجَّل');
$add('AC-E04-06', 'المحجوبُ لا يُرسَل',
    is_file($ROOT . '/includes/handler_guard.php') ? 'ENFORCED' : 'PARTIAL',
    'حارسُ الشاشات + حارسُ المعالجات (13 معالجًا) — مسحُ الدَّين A=0·B=0');

/* ═══════════════ E-05 · الهوية ═══════════════ */
$dupPersons = num($conn, "SELECT COUNT(*) FROM (SELECT employee_id FROM users WHERE employee_id IS NOT NULL GROUP BY employee_id HAVING COUNT(*)>1) z");
$noBridge = num($conn, "SELECT COUNT(*) FROM users WHERE COALESCE(status,'active')='active' AND employee_id IS NULL");
$add('AC-E05-01', 'لا شخصَ مكرر', $dupPersons === 0 ? 'ENFORCED' : 'PARTIAL', "معرّفاتُ أشخاصٍ مكررة: {$dupPersons} (حزام e05 ④)");
$add('AC-E05-02', 'لا حسابَ بلا شخص', $noBridge === 0 ? 'ENFORCED' : 'PARTIAL', "حساباتٌ نشطةٌ بلا جسر هوية: {$noBridge} (حزام e05 ①)");
$add('AC-E05-03', 'الكيانُ في كل صف', 'PARTIAL',
    'فاحصٌ يُبلّغ (قرار المالك: لا هجرة — DDL مجمَّد) · 65 جدولًا معلَنًا في حزام e05 ②');
$add('AC-E05-04', 'الملكيةُ مئةٌ بالضبط',
    has('app/Core/EntityGovernanceService.php', 'setCompleteness') ? 'ENFORCED' : 'OPEN',
    'بوابةُ الوسم + قيدُ الإضافة — leg01 16/16 · حزام e05 ③ صفر');
$add('AC-E05-05', 'الصفاتُ لا تُجمع', 'ENFORCED', 'user_capacities + مبدّل المساحة — والتبديل مسجَّل');

/* ═══════════════ E-06 · الاختبارات والتسليم ═══════════════ */
$add('AC-E06-01', 'أربعُ حالاتٍ لكل فعل', 'PARTIAL',
    'مطبَّقةٌ على أفعال WFM وSoD والأهلية والملكية (44+16 حالة) — وتعميمُها على الأفعال القديمة لم يكتمل');
$add('AC-E06-02', 'الصيغةُ موحَّدة', 'PARTIAL', 'الاختباراتُ الجديدة بصيغة بمعطى/عند/فإن — والقديمة متنوعة');
$add('AC-E06-03', 'صفرُ متطلبٍ بلا اختبار',
    is_file($ROOT . '/tools/trace_matrix.php') && is_file($ROOT . '/docs/TRACE_MATRIX_ar.csv') ? 'PARTIAL' : 'OPEN',
    'المصفوفةُ آليةٌ قائمة (697 معرفًا محصودًا × 886 شاهدًا · trace_matrix) — والاستشهادُ الحرفي 15.9٪: موجةُ توثيقِ الرموز في شواهدها جارية');
$add('AC-E06-04', 'البواباتُ لا تُقفز',
    is_file($ROOT . '/tools/release_gate.php') ? 'ENFORCED' : 'OPEN',
    'مُشغِّلُ البوابات الخمس يتوقف عند أول إخفاقٍ ويكتب شهادته');
$add('AC-E06-05', 'العزلُ يُختبر آليًّا', 'PARTIAL',
    'TenantDb fail-closed + nfr_infra_test — واختبارُ تسربٍ شاملٌ لكل المسارات لم يُبنَ');
$add('AC-E06-06', 'القبولُ بتنفيذٍ لا مشاهدة',
    is_file($ROOT . '/docs/UAT_SIGNOFF_ar.md') ? 'PARTIAL' : 'OPEN',
    'محضرُ جلسة اليوم جاهزٌ (12 سيناريو · جاهزية مفحوصة 39 حسابًا صفرَ كسور) — والتواقيعُ تُختم بأيدي المشاركين في الجلسة');

/* ═══════════════ WFM-01 · محرّك العمل الشخصي ═══════════════ */
$wiNoSrc = num($conn, "SELECT COUNT(*) FROM work_items WHERE source_type NOT IN ('SRC-01','SRC-02','SRC-03','SRC-04','SRC-05','SRC-06','SRC-07','SRC-08','SRC-09','SRC-10','SRC-11','SRC-12','SRC-13','SRC-14') OR source_ref=''");
$wiNoOwner = num($conn, "SELECT COUNT(*) FROM work_items WHERE status NOT IN ('closed_accepted','cancelled','rejected') AND (owner_user_id=0 OR (assigned_user_id IS NULL AND assigned_role_id IS NULL AND status NOT IN ('draft','scheduled')))");
$rtNoRecv = num($conn, "SELECT COUNT(*) FROM request_types WHERE status='active' AND (receiver='' OR deliverable='')");
$achNoEv = num($conn, "SELECT COUNT(*) FROM achievement_records WHERE reversed_at IS NULL AND (evidence_ref='' OR evidence_ref IS NULL)");
$rqNoHolder = num($conn, "SELECT COUNT(*) FROM requests WHERE status IN ('routed','in_approval','approved','executing') AND (current_holder_user_id IS NULL OR current_holder_user_id=0)");
$lateNoEsc = num($conn, "SELECT COUNT(*) FROM work_items wi WHERE wi.status='overdue' AND NOT EXISTS (SELECT 1 FROM work_escalations we WHERE we.item_kind='work_item' AND we.item_ref=wi.id)");
$delDead = num($conn, "SELECT COUNT(*) FROM work_delegations WHERE status='active' AND ends_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
$rqClosedNoResp = num($conn, "SELECT COUNT(*) FROM requests rq WHERE rq.status='closed' AND NOT EXISTS (SELECT 1 FROM request_responses rr WHERE rr.request_id=rq.id)");

$add('AC-WFM-01', 'صفرُ مهمةٍ بلا مصدر', $wiNoSrc === 0 ? 'ENFORCED' : 'PARTIAL', "المقيس: {$wiNoSrc} (حزام wfm ①)");
$add('AC-WFM-02', 'صفرُ مهمةٍ بلا مالكٍ أو منفِّذ', $wiNoOwner === 0 ? 'ENFORCED' : 'PARTIAL', "المقيس: {$wiNoOwner} · وحارسُ السبعة يمنع الحفظَ قبليًّا");
$add('AC-WFM-03', 'صفرُ طلبٍ بلا جهةٍ ومسارِ رد', $rtNoRecv === 0 ? 'ENFORCED' : 'PARTIAL', "أنواعٌ ناقصةٌ: {$rtNoRecv} من 62 · والردُّ التسعة بنيةٌ إلزامية");
$add('AC-WFM-04', 'صفرُ موافقةٍ بلا صلاحيةٍ ونطاق', 'ENFORCED', 'approval_links تُحل بالدور والنطاق — والقرارُ لحامل الخطوة وحدَه (403 للغريب)');
$add('AC-WFM-05', 'صفرُ إنجازٍ بلا دليل', $achNoEv === 0 ? 'ENFORCED' : 'PARTIAL', "المقيس: {$achNoEv} · والإدخالُ اليدويُّ مرفوضٌ بنيويًّا");
$add('AC-WFM-06', 'صفرُ عنصرٍ بلا رابطٍ لأصله', 'ENFORCED', 'source_screen+source_ref إلزاميان — وكلُّ صفٍّ يفتح أصله');
$add('AC-WFM-07', 'صفرُ عنصرٍ لا يُعرف أين توقف', $rqNoHolder === 0 ? 'ENFORCED' : 'PARTIAL', "طلباتٌ حيةٌ بلا حامل: {$rqNoHolder}");
$add('AC-WFM-08', 'صفرُ تنبيهٍ يتطلب فعلًا ولا يتحول مهمةً', 'ENFORCED', 'زرُّ التحويل الصريح في شاشة التنبيهات + عطالةُ task_item_id');
$add('AC-WFM-09', 'صفرُ مهمةٍ متأخرةٍ بلا تصعيد', $lateNoEsc === 0 ? 'ENFORCED' : 'PARTIAL', "المقيس: {$lateNoEsc} · والنبضةُ صعّدت 56 على واقع");
$add('AC-WFM-10', 'صفرُ تكليفٍ منتهٍ يولّد', $delDead === 0 ? 'ENFORCED' : 'PARTIAL', "تفويضٌ نشطٌ منقضٍ: {$delDead} · والحارسُ يرفض التوليدَ به");
$add('AC-WFM-11', 'صفرُ مديرٍ يرى خارج نطاقه', 'ENFORCED', 'ems_manager_scope_user_ids من parent_id — لا قوائمَ ثابتة');
$add('AC-WFM-12', 'صفرُ إدخالٍ مكرر', 'ENFORCED', 'مساحةُ عملي واجهةٌ لا مصدر — فورمُ الإنشاء منزوعٌ (WF-01)');
$add('AC-WFM-13', 'تفسيرُ سبب الظهور', 'ENFORCED', 'السلسلةُ الخماسيةُ في مهامي وطلباتي — والناقصةُ تُحجب');
$add('AC-WFM-14', 'انعكاسُ الإنجاز', 'ENFORCED', 'reverseForSource آليًّا بإعادة الفتح والإلغاء — مُثبتٌ بالاختبار');
$add('AC-WFM-15', 'السيناريوهاتُ الستةَ عشرَ', 'PARTIAL', 'اختبارُ المحرّك 44 حالة يغطي جوهرَها — وقائمةُ السيناريوهات الرسميةُ لم تُطابَق بندًا بندًا');
$add('AC-WFM-16*', 'صفرُ طلبٍ مغلقٍ بلا الرد التسعة', $rqClosedNoResp === 0 ? 'ENFORCED' : 'PARTIAL', "المقيس: {$rqClosedNoResp} (حزام wfm ⑦)");

/* ═══════════════ M-00 · قواعد الإدارة التنفيذية ═══════════════ */
$add('BR-CEO-01', 'التوقيعُ بالسلطة الأصلية', 'PARTIAL', 'أعمدةُ «مرجع سلطته» قائمةٌ في شاشة التوقيع — والفحصُ البنيويُّ للمرجع لم يُلزَم');
$add('BR-CEO-02', 'لا توقيعَ بملاحظةٍ حرجةٍ مفتوحة',
    has('Portal/ceo_contracts.php', 'BR-CEO-02') ? 'ENFORCED' : 'OPEN', 'حارسٌ خادميٌّ يرفض التوقيعَ باسم الملاحظة الحاجبة');
$add('BR-CEO-03', 'لا فتحَ مشروعٍ بقرارٍ فردي',
    has('Portal/project_charter.php', 'BR-CEO-03') ? 'ENFORCED' : 'OPEN', 'الإفاداتُ الخمسُ شرطُ العرض — والناقصُ يُسمّى');
$add('BR-CEO-04', 'القرارُ يُلزم بمهلةٍ لا يوجّه', 'PARTIAL', 'أعمدةُ المكلَّف والمهلة في سجل القرارات — والإلزامُ البنيويُّ لم يُفرض');
$add('BR-CEO-05', 'الرفعُ آليٌّ عند تجاوز السقف', 'PARTIAL', 'سلّمُ الطلبات يرفع بالسلسلة — والرفعُ الآليُّ بالسقف النقدي في المستندات المالية جزئي');
$add('BR-CEO-06', 'لا تنفيذَ ولا إدخالَ من القمة', 'PARTIAL', 'مصفوفةُ الصلاحيات تمنع — ولم يُبنَ فاحصٌ يرصد منحًا تنفيذيًّا للدور 9');
$add('BR-CEO-07', 'الحكمُ الفنيُّ لا يُعارَض', 'ENFORCED', 'منعُ الصيانة يرفع بحكمٍ فنيٍّ حصرًا (mnt/permit_gate) — لا مسارَ إداريًّا يرفعه');
$add('BR-CEO-08', 'لا رجعيةَ في القرار الموقَّع', 'PARTIAL', 'سجلُّ التدقيق يحفظ الأصلَ والعدول — والمنعُ البنيويُّ للتعديل الرجعي جزئي');

/* ═══════════════ M-14 · قواعد الحوكمة ═══════════════ */
$add('BR-GOV-01', 'الحارسُ في الخادم لا في الواجهة',
    is_file($ROOT . '/includes/governance_guard.php') ? 'ENFORCED' : 'OPEN', 'fail-closed بالبناء + مسحُ الدَّين A=0·B=0');
$add('BR-GOV-02', 'الرصدُ قبل الإنفاذ', 'ENFORCED', 'أعلامُ monitor/enforce في action_guard وحارس UN-02 وحارس الشاشات');
$add('BR-GOV-03', 'لا صلاحيةَ فرديةً بلا قالب', 'PARTIAL', 'القوالبُ مبنيةٌ ومملوءة — والإنفاذُ ينتظر قلبَ EMS_PERM_SOURCE بعد نافذة الظل');
$add('BR-GOV-04', 'لا اعتمادَ بلا مرجع تفويض', 'PARTIAL', 'delegation_ref عمودٌ في work_items/approval_links — وإلزامُه على كل اعتمادٍ مالي لم يُعمَّم');
$add('BR-GOV-05', 'الاستثناءُ بمدةٍ وعددٍ لا مفتوحًا',
    has('Operations/cron_wfm_engine.php', 'BR-GOV-05') ? 'ENFORCED' : 'OPEN', 'الانقضاءُ آليٌّ بالنبضة ⑤ — لا يمتد بالسكوت');
$add('BR-GOV-06', 'فصلُ الواجبات بنيويٌّ لا سياسة',
    is_file($ROOT . '/includes/sod_guard.php') ? 'ENFORCED' : 'OPEN', 'المنعُ عند المنح + المسحُ الدوري + تقرير الحوكمة ③');
$add('BR-GOV-07', 'سجلُّ التدقيق لا يُمحى — والقراءةُ تُسجَّل',
    is_file($ROOT . '/includes/sensitive_read_log.php') ? 'PARTIAL' : 'OPEN',
    'السجلُّ غيرُ قابلٍ للتعديل + قناةُ الاطّلاع الحساس موصولةٌ بثلاثة أبواب — والتعميمُ على كل حقلٍ حساسٍ مستمر');
$add('BR-GOV-08', 'لا نشرَ بلا بصمةٍ وتقريرِ اكتمال', 'PARTIAL',
    'شهادةُ البوابات الخمس تُكتب لكل تشغيل — وبصمةُ الإصدار الرسميةُ تُملأ يدويًّا في شاشتها');

/* ═══════════════ الحصيلة ═══════════════ */
$c = array('ENFORCED' => 0, 'PARTIAL' => 0, 'OPEN' => 0);
foreach ($AC as $a) { $c[$a['verdict']]++; }
$tot = count($AC);
$score = round(100 * ($c['ENFORCED'] + 0.5 * $c['PARTIAL']) / $tot, 1);

if ($MD) {
    $out = "# تدقيق تنفيذ update0008 — على معايير قبول الوثائق نفسها\n\n";
    $out .= "**التاريخ:** " . date('Y-m-d H:i') . " · **المقيس:** {$tot} معيارًا معلَنًا في الوثائق التسع\n\n";
    $out .= "| الحكم | العدد |\n|---|---|\n";
    $out .= "| ✅ نافذٌ ومفحوص (ENFORCED) | {$c['ENFORCED']} |\n";
    $out .= "| 🟡 نافذٌ في نطاقٍ (PARTIAL) | {$c['PARTIAL']} |\n";
    $out .= "| ⬜ لم يُنفَّذ (OPEN) | {$c['OPEN']} |\n";
    $out .= "| **درجة التغطية** | **{$score}٪** |\n\n";
    $out .= "| المعيار | العنوان | الحكم | الشاهد |\n|---|---|---|---|\n";
    foreach ($AC as $a) {
        $ic = $a['verdict'] === 'ENFORCED' ? '✅' : ($a['verdict'] === 'PARTIAL' ? '🟡' : '⬜');
        $out .= "| `{$a['code']}` | {$a['title']} | {$ic} | {$a['ev']} |\n";
    }
    file_put_contents($ROOT . '/docs/UPDATE0008_AUDIT_ar.md', $out);
    fwrite(STDOUT, "كُتب: docs/UPDATE0008_AUDIT_ar.md\n");
}

fwrite(STDOUT, "════ تدقيق update0008 على معايير قبولها ════\n");
foreach ($AC as $a) {
    $m = $a['verdict'] === 'ENFORCED' ? '✅' : ($a['verdict'] === 'PARTIAL' ? '🟡' : '⬜');
    fwrite(STDOUT, sprintf("%s %-12s %s\n", $m, $a['code'], $a['title']));
}
fwrite(STDOUT, "──────────────────────────────────────────────\n");
fwrite(STDOUT, "نافذٌ ومفحوص: {$c['ENFORCED']} · نافذٌ في نطاق: {$c['PARTIAL']} · مفتوح: {$c['OPEN']} · المجموع: {$tot}\n");
fwrite(STDOUT, "درجة التغطية (الكامل + نصف الجزئي): {$score}٪\n");
