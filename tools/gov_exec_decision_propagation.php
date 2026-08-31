<?php
/**
 * tools/gov_exec_decision_propagation.php — قياسُ نفاذِ القراراتِ المعتمدةِ قرارًا قرارًا (GOV_EXEC §8)
 * ═══════════════════════════════════════════════════════════════════════════
 * لكلِّ قرارٍ معتمدٍ في `repair01_decisions` مجسٌّ مسمًّى:
 *   TABLE_PROBE  — أثرُ القرارِ المسمّى جدولًا حيًّا في المخطَّطِ (بعدِّ صفوفِه).
 *   ENGINE_FILE  — أثرُه المسمّى محرّكًا/ملفًّا حيًّا على القرص.
 *   REQ_LEDGER   — أثرُه مقيَّدًا في دفترِ المتطلّباتِ بحالتِه.
 *   PENDING_CFG  — معتمدُ الآليّةِ مؤجَّلُ القيم ⇒ BLOCKED_OWNER_VALUES باسمِه.
 * والباقي `UNPROPAGATED` يُطبع ليُحكم يدويًّا في الجولةِ — لا أخضرَ بالفراغ.
 * التشغيل: php tools/gov_exec_decision_propagation.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
date_default_timezone_set((string) ems_env('EMS_APP_TIMEZONE', 'Africa/Cairo'));
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$SNAP = 'BL-' . date('Ymd') . '-' . trim(shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

/* الجداولُ الحيّة */
$tables = array();
$q = $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
while ($r = $q->fetch_row()) { $tables[strtolower($r[0])] = true; }

/* ملفّاتُ المحرّكات */
$engineFiles = array();
foreach (array('app/Services', 'app/Core', 'includes', 'tools') as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
            $engineFiles[strtolower(substr($f->getFilename(), 0, -4))] = str_replace($ROOT . '/', '', str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname()));
        }
    }
}

/* ═══ الخريطةُ المُحكمة: قرارٌ ⇒ مجسُّه المسمّى — والمجسُّ يُنفَّذ لا يُصدَّق ═══
   الصيغ: t:جدول (وجودٌ وعدُّ صفوف) · f:ملف · q:استعلامٌ يُنتظر >0 ·
   q0:استعلامٌ يُنتظر =0 (برهانُ نفيٍ) · target:أساسٌ (مُسقَطٌ هدفًا والبناءُ بترتيبِ السجلّ) */
$CURATED = array(
    'DEC-ACC-01' => array('t:fin_monthly_close', 'تقويمُ الإقفالِ كياناتٌ حيّة (شهري/نهائي/تعاقدي ثلاثتُها جداول)'),
    'DEC-ACC-02' => array('t:fin_fx_rates', 'محرّكُ الصرفِ بجدولَي الأسعارِ والفروق — base=amount×rate'),
    'DEC-ACC-03' => array('t:fin_cost_records', 'سجلّاتُ التكلفةِ حيّةٌ — وتعميقُ متوسّطِ التكلفةِ ببندِ حملةِ الماليّة'),
    'DEC-ACC-04' => array('t:fin_depreciation', 'الإهلاكُ عند الماليّةِ وساعاتُ الاستخدامِ تُستورد قراءةً'),
    'DEC-CEO-01' => array('t:exec_decisions', 'سجلُّ القراراتِ التنفيذيّةِ بنافذةِ توثيقِه'),
    'DEC-CEO-02' => array('t:risk_acceptances', 'السلطةُ المحجوزةُ مسجَّلةٌ (قبولُ المخاطرِ بحدودِه)'),
    'DEC-CEO-03' => array("q:SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict='EXECUTIVE_PROJECTION'", 'الأسطحُ القياديّةُ إسقاطاتٌ مصنَّفةٌ لا نسخ'),
    'DEC-CEO-04' => array('t:gov_delegations', 'محرّكُ الإنابةِ حيٌّ — وربطُ دورِ النوّابِ بقرارِ تنظيمٍ (كشفُ EX-DVP القائم)'),
    'DEC-CEO-05' => array("q:SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code='EX-CEO'", 'الأسطحُ القياديّةُ مسجَّلةٌ رسميًّا في سجلِّ الشاشات'),
    'DEC-ENT-01' => array("q:SELECT COUNT(*) FROM repair01_screen_registry WHERE source_of_truth <> ''", 'مصدرُ الحقيقةِ مسجَّلٌ سطحًا سطحًا وقيدُ sot_witness يحرسه'),
    'DEC-ENT-02' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE kind='DEPARTMENT'", 'التنظيمُ من مساحاتِ الإداراتِ لا من موجاتِ الإصلاح'),
    'DEC-ENT-03' => array("q:SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME REGEXP '_line'", 'كلُّ 1:N في Child Register — جداولُ البنودِ حيّة'),
    'DEC-ENT-04' => array("q:SELECT COUNT(*) FROM repair01_fields WHERE field_type='DERIVED'", 'المشتقُّ صنفٌ محكومٌ صفرَ إدخالٍ (المحظورُ DERIVED+Editable بنصِّ الورقة)'),
    'DEC-ENT-05' => array("q0:SELECT COUNT(*) FROM ems_event_deliveries WHERE fail_text LIKE '%EFFECT_MISSING%'", 'كلُّ حدثٍ يستلزم أثرًا بعقدِه — EFFECT_MISSING=0 نمطًا'),
    'DEC-ENT-06' => array('t:gov_ladders', 'محرّكُ الاعتمادِ الواحدُ حيٌّ — وقيمُه عند بوّابةِ OA-06'),
    'DEC-ENT-07' => array('f:includes/post_contract.php', 'القاعدةُ الحرجةُ في الخادمِ (عقدُ POST بفعلٍ مسجَّلٍ وصلاحيةٍ وIdempotency)'),
    'DEC-ENT-08' => array('f:app/Core/TenantDb.php', 'حصانةُ immutable_key في البوّابةِ — التصحيحُ حدثًا/نسخةً لا دهسًا'),
    'DEC-ENT-09' => array('f:includes/security.php', 'سجلُّ الرفضِ الموحَّدُ حيّ — log_security_event باسطحِه الاربعة'),
    'DEC-ENT-10' => array("q:SELECT COUNT(*) FROM ems_event_deliveries", 'حالاتُ التسليمِ الكاملةُ مع نبضِ cron_events المعلَن'),
    'DEC-FAIL-01' => array('target:نموذجُ أبعادِ العطلِ الثمانيةِ مُسقَطٌ في متطلّباتِ W07/W14 — والبناءُ بترتيبِ السجلّ'),
    'DEC-FAIL-02' => array('t:monthly_performance_downtime', 'تقطيعُ التوقّفِ الزمنيُّ سجلٌّ حيّ'),
    'DEC-FAIL-03' => array('target:ساعةُ العطلِ من لحظةِ التوقّفِ — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ'),
    'DEC-FAIL-04' => array('target:فرزُ التوقّفِ المخطَّطِ وStandby — مُسقَطٌ هدفًا والبناءُ بترتيبِ السجلّ'),
    'DEC-FAIL-05' => array('t:work_escalations', 'إقرارُ صاحبِ القطاعِ والتحكيمُ — سلسلةُ التصعيدِ حيّة'),
    'DEC-FIN-01' => array('f:app/Services/Financing/FinancingService.php', 'نموذجُ الدفعِ المستهدفُ ببابِ خدمتِه المقيَّد'),
    'DEC-FIN-02' => array('f:app/Services/Financing/FinancingService.php', 'التعثّرُ لا يعلّق آليًّا — البوّابةُ يدويّةٌ في الخدمة'),
    'DEC-FIN-03' => array('t:fin_final_close', 'الإقفالاتُ الثلاثةُ كياناتٌ متمايزة (شهري/نهائي/تعاقدي)'),
    'DEC-FLEET-01' => array('t:exception_requests', 'المسارُ الاستثنائيُّ الموثَّقُ سجلٌّ حيٌّ باعتمادِه واستهلاكِه'),
    'DEC-FLEET-02' => array('t:gov_ladders', 'تصرّفُ الأصلِ عبر محرّكِ الاعتماد'),
    'DEC-FLEET-03' => array('f:app/Services/Unit/TimesheetEntryService.php', 'الساعاتُ من التشغيلِ/الأسطولِ مصدرًا واحدًا'),
    'DEC-FLEET-04' => array('t:fleet_depreciation_profile', 'سياسةُ الإهلاكِ وقيمتُه عند الماليّةِ بملفِّها وتدقيقِه'),
    'DEC-GOV-01' => array("q:SELECT COUNT(*) FROM roles WHERE id=15", 'الحوكمةُ تملك السياسةَ ودورُ مديرِ الصلاحياتِ ينفّذ'),
    'DEC-GOV-02' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='IAF'", 'المراجعةُ الداخليّةُ مساحةٌ مستقلّةٌ خارجَ الحوكمة'),
    'DEC-GOV-03' => array('t:gov_investigation', 'قضيّةُ الحوكمةِ سجلٌّ حيٌّ بشرطِ الخرق'),
    'DEC-GOV-04' => array("q:SELECT COUNT(*) FROM risk_register", 'سجلُّ المخاطرِ واحدٌ عند إدارتِه — ولا سجلَّ موازيًا في الحوكمة'),
    'DEC-HR-01' => array('t:payroll_time_inputs', 'حافزُ الإنتاجِ من مدخلاتِ الوقتِ اشتقاقًا'),
    'DEC-HR-02' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-07'", 'الموارد تملك الشخصَ — ولا وحدةَ أفرادِ مشاريعَ موازية'),
    'DEC-HR-03' => array('t:payroll_runs', 'رأسُ المسيّرِ وبنودُ الموظّفين Child'),
    'DEC-HR-04' => array('target:رأسُ القرضِ وأقساطُه Child — مُسقَطٌ في متطلّباتِ الموارد والبناءُ بترتيبِ السجلّ'),
    'DEC-HR-05' => array('t:hr_disciplinary_case', 'القضيّةُ التأديبيّةُ منفصلةٌ والخصمُ بمرجعِها (سلّمُ الخصمِ بثلاثِ أيدٍ)'),
    'DEC-INV-01' => array('t:hr_disciplinary_case', 'السلوكُ الوظيفيُّ يوجَّه للموارد بسجلِّه'),
    'DEC-INV-02' => array('t:gov_investigation', 'تحقيقُ الالتزامِ عند الحوكمةِ بسجلِّه'),
    'DEC-INV-03' => array('t:gov_investigation', 'سلسلةُ التصعيدِ بتعارضِ المصلحةِ بالحالة'),
    'DEC-MNT-01' => array('target:شهادةُ العودةِ للخدمةِ — مُسقَطةٌ في متطلّباتِ W07 والبناءُ بترتيبِ السجلّ'),
    'DEC-MNT-02' => array('f:Governance/sod_conflicts.php', 'استقلالُ الفاحصِ/المصدّقِ ضمن فصلِ الواجباتِ الحيّ'),
    'DEC-MNT-03' => array('target:جدولةُ OEM أساسًا — مُسقَطةٌ في متطلّباتِ الصيانةِ والبناءُ بترتيبِ السجلّ'),
    'DEC-MY-01' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='WS-MY' AND kind='PERSONAL'", 'مساحةُ عملي شخصيّةٌ لا تملك طلبَ Domain'),
    'DEC-MY-02' => array('t:request_routes', 'طلباتي مُطلِقٌ موحَّدٌ بإسقاطٍ — وسجلُّ التوجيهِ حيّ'),
    'DEC-MY-03' => array('t:request_routes', 'نوعُ الطلبِ يحدّد وجهتَه من سجلٍّ مركزيّ'),
    'DEC-OPEN-03' => array('f:app/Core/TenantRegistry.php', 'البعدُ الكيانيُّ معماريٌّ — 574 إعلانَ جدولٍ بعزلِ company_id'),
    'DEC-OPEN-12' => array('target:تصنيفُ العطلِ بالأثرِ لا بالمدّةِ وحدَها — مُسقَطٌ هدفًا والبناءُ بترتيبِ السجلّ'),
    'DEC-OPEN-13' => array('t:ticket_sla_policies', 'عتباتُ الاستجابةِ سياساتٌ حيّةٌ لا أرقامًا في الشيفرة'),
    'DEC-OPEN-14' => array('t:ticket_escalation_rules', 'العتبةُ المركّبةُ قواعدُ تصعيدٍ حيّة'),
    'DEC-OPEN-16' => array('t:gov_investigation', 'التحقيقُ اختصاصٌ أصيلٌ لمالكِ موضوعِه — سجلا التوجيهِ حيّان'),
    'DEC-OPEN-17' => array('t:request_routes', 'الحوكمةُ تملك حوكمةَ السجلِّ وكلُّ إدارةٍ تسجّل أنواعَها'),
    'DEC-OPEN-18' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id REGEXP '^DEP-(0[1-9]|1[0-7])$'", 'الترقيمُ المؤسّسيُّ 01..17 نافذٌ في المساحات'),
    'DEC-OPS-01' => array('f:app/Core/TenantDb.php', 'لا تعديلَ رجعيًّا بعد الاعتماد — حصانةُ الدفاترِ في البوّابة'),
    'DEC-ORG-01' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE kind='DEPARTMENT'", 'السبعَ عشرةَ إدارةً بمساحاتِها الحيّة'),
    'DEC-ORG-02' => array("q:SELECT COUNT(*) FROM nav_ws_roles WHERE workspace_id='DEP-11' AND binding='PRIMARY'", 'التشغيلُ رأسُ القطاعِ بدورِه الحيّ'),
    'DEC-ORG-03' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-12'", 'إدارةُ الموقعِ طبقةٌ إداريّةٌ بمساحتِها'),
    'DEC-ORG-04' => array('t:assignment_capabilities', 'فريقُ المنجمِ تكوينٌ تشغيليٌّ بالقدراتِ لا إدارة'),
    'DEC-ORG-05' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-10'", 'البلاغاتُ إدارةٌ بمساحتِها'),
    'DEC-ORG-06' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE kind='EXECUTIVE'", 'القيادةُ مساحاتٌ تنفيذيّةٌ خارجَ تعدادِ الإدارات'),
    'DEC-ORG-07' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE kind='PERSONAL'", 'مساحةُ عملي شخصيّةٌ لا إدارة'),
    'DEC-ORG-08' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='IAF'", 'المراجعةُ وظيفةُ توكيدٍ مستقلّةٌ خارجَ الإدارات'),
    'DEC-ORG-09' => array("q0:SELECT COUNT(*) FROM nav_workspaces WHERE name_ar LIKE '%HSE%' OR name_ar LIKE '%السلامة والصحة%'", 'لا إدارةَ HSE — برهانُ نفيٍ حيّ'),
    'DEC-ORG-10' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-16'", 'المشترياتُ إدارةٌ بوظائفِها الاستراتيجيّةِ مجموعةً داخلَها لا إدارةً مركزيّةً فوقَ الإدارات'),
    'DEC-ORG-11' => array('t:workforce_requirement', 'الموارد تملك الشخصَ والعقدَ — والقوى تقرأ بالمفتاح'),
    'DEC-PRC-01' => array('t:gov_ladders', 'حدودُ الإسنادِ المباشرِ من السياسةِ/السلّمِ لا Hardcode'),
    'DEC-PRC-02' => array('t:proc_orderpoint', 'نقطةُ الطلبِ تنشئ مسودّةً فقط'),
    'DEC-PRC-03' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-16'", 'المجموعةُ الاستراتيجيّةُ داخل إدارةِ المشتريات'),
    'DEC-PRC-04' => array('t:exception_requests', 'مساراتُ الاستثناءِ workflow مسجَّلٌ باعتمادِه'),
    'DEC-ROUTE-01' => array('t:request_routes', 'الحوكمةُ تملك بنيةَ سجلِّ التوجيه'),
    'DEC-RSK-01' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-09'", 'المخاطرُ إدارةٌ مستقلّةٌ بمساحتِها'),
    'DEC-RSK-02' => array('t:risk_units', 'عائلاتُ المخاطرِ بأصنافِها الحيّة'),
    'DEC-RSK-03' => array('t:risk_appetite', 'شهيّةُ المخاطرِ سجلٌّ يعتمدُه صاحبُ السلطةِ المحجوزة'),
    'DEC-RSK-04' => array('t:risk_acceptances', 'قبولُ المخاطرِ داخل الحدودِ بسجلِّه'),
    'DEC-RSK-05' => array('t:risk_assessments', 'مصفوفةُ 5×5 بأبعادِ الأثرِ حيّة'),
    'DEC-RSK-06' => array('t:risk_signals', 'المخاطرُ تقرأ أحداثَ المصدرِ بالمفتاحِ لا نسخًا تشغيليّة'),
    'DEC-SAL-01' => array('t:claims', 'المطالبةُ المرحليّةُ بشرطِ عقدِها (دورةُ المطالبات الحيّة)'),
    'DEC-SAL-02' => array('t:gov_ladders', 'السلّمُ يحدّد المعتمِدَ ولا ينشئ حقًّا تعاقديًّا'),
    'DEC-SITE-01' => array('f:app/Services/Unit/TimesheetEntryService.php', 'يومٌ×ورديّةٌ برأسِه وبنودِه (round_no)'),
    'DEC-SITE-02' => array('target:الموقعُ يملك السكنَ والإعاشةَ — مُسقَطٌ في متطلّباتِ DEP-12 والبناءُ بترتيبِ السجلّ'),
    'DEC-SITE-03' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE kind='DEPARTMENT'", 'التبعيّةُ من التنظيمِ لا من موجاتِ الإصلاح'),
    'DEC-SUP-01' => array('target:ترشيحُ البديلِ آليًّا والإحلالُ بقرارٍ — مُسقَطٌ في متطلّباتِ الموردين والبناءُ بترتيبِ السجلّ'),
    'DEC-SUP-02' => array('t:sup_close', 'استحقاقُ الموردِ دورةٌ مستقلّةٌ عن التحصيل'),
    'DEC-SURF-01' => array('f:tests/gov_m114_scope_filter_proof.php', 'بوّابةُ ازدواجِ المصدرِ شاهدٌ حيٌّ بمِجَسِّه السالب'),
    'DEC-SURF-02' => array("q:SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict='DOMAIN_PROJECTION'", 'تقريرُ الجمعِ إسقاطٌ مصنَّفٌ لا مالك'),
    'DEC-SURF-03' => array("q:SELECT COUNT(*) FROM repair01_platform_capabilities", 'سطحُ الجميعِ إسقاطٌ منصّيٌّ مسجَّل'),
    'DEC-SURF-04' => array("q:SELECT COUNT(*) FROM nav_placements WHERE placement_type='TAB_CHILD'", 'الدمجُ تبويبًا بقاعدةٍ مكتوبةٍ في source_ref كلِّ موضع'),
    'DEC-SURF-05' => array('f:assets/css/ems-filters.css', 'مكوّنُ التصفيةِ المركزيُّ الواحدُ للأسطحِ المؤهَّلة'),
    'DEC-SURF-06' => array('f:tools/baseline_reconcile.php', 'موانعُ المصالحةِ الثلاثةُ في أداتِها'),
    'DEC-SURF-07' => array('f:docs/REPAIR01_20260823/GATE00_BASELINE.md', 'انضباطُ اللقطةِ: شجرةٌ نظيفةٌ وHEAD ثابتٌ (عقدُ GATE-00 المطبَّقُ في كلِّ قصّ)'),
    'DEC-SURF-08' => array('t:nav_canonical', 'لا اسمَ معروضًا غيرَ معتمدٍ — القاموسُ المعياريُّ يغلب'),
    'DEC-TKT-01' => array("q:SELECT COUNT(*) FROM nav_workspaces WHERE workspace_id='DEP-10'", 'البلاغاتُ مصدرُ حقيقةِ دورةِ البلاغ'),
    'DEC-TKT-02' => array('t:request_routes', 'البلاغُ يوجّه والكيانُ يُنشأ في إدارتِه'),
    'DEC-TKT-03' => array('target:أدوارُ البلاغِ الأربعةُ المنفصلةُ — مُسقَطةٌ في متطلّباتِ DEP-10 والبناءُ بترتيبِ السجلّ'),
    'DEC-TKT-04' => array('t:ticket_escalations', 'سجلّاتُ البلاغِ التابعةُ Child حيّة'),
    'DEC-TKT-05' => array('t:ticket_sla_policies', 'الإغلاقُ الآليُّ بنافذةِ تحقّقٍ من سياسةٍ حيّة'),
    'DEC-TKT-06' => array('t:ticket_escalation_rules', 'الحرجةُ لا تُغلق صمتًا — قواعدُ الحراسةِ حيّة'),
    'DEC-TKT-07' => array('t:request_routes', 'سجلُّ كياناتِ الموضوعِ مركزيّ'),
    'DEC-TRP-01' => array('t:trp_trip_leg', 'أرجلُ الرحلةِ Child منذ الآن'),
    'DEC-TRP-02' => array('t:transfer_delivery_docs', 'التشغيلُ يطلب والنقلُ يملك التنفيذَ بعقدِ أمرِه'),
    'DEC-TRS-01' => array('t:permission_approval_steps', 'رقابةٌ ثنائيّةٌ للتحويلِ الخارجيّ (خطواتُ اعتمادٍ لا فعلُ فردٍ)'),
    'DEC-TRS-02' => array('t:tre_petty_expense', 'النثريّةُ ضمن حدِّ سياسةٍ بسجلِّها وعهدتِها'),
    'DEC-TRS-03' => array('t:fin_financial_events', 'طلبُ الدفعِ من الإدارةِ المستحقّةِ عبر بوّابةِ D05 والخزينةُ تنفّذ'),
    'DEC-TRS-04' => array('target:توزيعُ التحصيلِ على فواتيرَ Child — مُسقَطٌ في متطلّباتِ الخزينةِ والبناءُ بترتيبِ السجلّ'),
    'DEC-VP-01' => array('t:gov_delegations', 'التفويضُ الجزئيُّ المؤقّتُ بحدودِه ومدّتِه وسجلِّه'),
    'DEC-WH-01' => array('t:proc_item_track_rule', 'التتبّعُ Lot/Serial/Expiry من دليلِ الأصنافِ شرطيًّا'),
    'DEC-WH-02' => array('t:proc_wh_custodian', 'المخزنُ يملك سجلَّ العهدةِ — وسجلُّ الإسنادِ الجديدُ بحبّتِه (بُني في هذه الجولة)'),
    'DEC-WH-03' => array('t:proc_issue_request_line', 'المطلوبُ/المعتمدُ/المصروفُ على مستوى البنود'),
    'DEC-WRK-01' => array('t:positions', 'كتالوجُ المناصبِ منفصلٌ عن كتالوجِ القدرات (كلاهما حيّ)'),
    'DEC-WRK-02' => array('t:work_escalations', 'التصعيدُ عند غيابِ البديلِ بمحرّكِه'),
    'DEC-WRK-03' => array("q:SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='employees'", 'لا تكرارَ لسجلِّ الشخصِ — القوى تقرأ بالمفتاحِ الأجنبيّ'),
);

$ins = $conn->prepare("INSERT INTO gov_decision_propagation
    (decision_id, verdict, probe_kind, probe_ref, basis, snapshot_id)
    VALUES (?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE verdict = VALUES(verdict), probe_kind = VALUES(probe_kind),
        probe_ref = VALUES(probe_ref), basis = VALUES(basis),
        measured_at = NOW(), snapshot_id = VALUES(snapshot_id)");

$counts = array(); $unprop = array();
$q = $conn->query("SELECT decision_id, domain, owner_decision, config_pending_stage, blocker_type,
        affected_rules, code_impact, migration_impact FROM repair01_decisions WHERE status = 'APPROVED' ORDER BY decision_id");
while ($d = $q->fetch_assoc()) {
    $id = $d['decision_id'];
    $hay = $d['owner_decision'] . ' ' . $d['affected_rules'] . ' ' . $d['code_impact'] . ' ' . $d['migration_impact'];
    $verdict = null; $kind = ''; $ref = ''; $basis = '';

    /* ⓪ معتمدُ الآليّةِ مؤجَّلُ القيمِ يُحكم بحاجزِه أوّلًا — قبل أيِّ مجسِّ وجود */
    if ((string) $d['config_pending_stage'] !== '' || (string) $d['blocker_type'] !== '') {
        $verdict = 'BLOCKED_OWNER_VALUES'; $kind = 'PENDING_CFG';
        $ref = 'config_pending_stage=' . $d['config_pending_stage'] . ' blocker=' . $d['blocker_type'];
        $basis = 'آليّتُه معتمدةٌ نافذةُ البناءِ وقيمُه بانتظارِ المالك — يُحكم عند بوّابةِ قيمِه';
    }

    /* ① الخريطةُ المُحكمة — والمجسُّ يُنفَّذ لا يُصدَّق */
    if ($verdict === null && isset($CURATED[$id])) {
        $spec = $CURATED[$id][0];
        $why = isset($CURATED[$id][1]) ? $CURATED[$id][1] : '';
        if (strpos($spec, 't:') === 0) {
            $tbl = substr($spec, 2);
            if (isset($tables[strtolower($tbl)])) {
                $n = (int) $conn->query('SELECT COUNT(*) FROM `' . $tbl . '`')->fetch_row()[0];
                $verdict = 'RUNTIME_PRESENT'; $kind = 'TABLE_PROBE'; $ref = $tbl . ' (' . $n . ' صفًّا)'; $basis = $why;
            }
        } elseif (strpos($spec, 'f:') === 0) {
            $fp = substr($spec, 2);
            if (is_file($ROOT . '/' . $fp)) {
                $verdict = 'RUNTIME_PRESENT'; $kind = 'ENGINE_FILE'; $ref = $fp; $basis = $why;
            }
        } elseif (strpos($spec, 'q0:') === 0) {
            $r0 = $conn->query(substr($spec, 3));
            if ($r0 !== false) {
                $v0 = (int) $r0->fetch_row()[0];
                if ($v0 === 0) { $verdict = 'RUNTIME_VERIFIED'; $kind = 'NEGATIVE_PROBE'; $ref = 'قيس = 0'; $basis = $why; }
            }
        } elseif (strpos($spec, 'q:') === 0) {
            $r0 = $conn->query(substr($spec, 2));
            if ($r0 !== false) {
                $v0 = (int) $r0->fetch_row()[0];
                if ($v0 > 0) { $verdict = 'RUNTIME_VERIFIED'; $kind = 'SQL_PROBE'; $ref = 'قيس = ' . $v0; $basis = $why; }
            }
        } elseif (strpos($spec, 'target:') === 0) {
            $verdict = 'TARGET_PROPAGATED_BUILD_PENDING'; $kind = 'TARGET_RULING'; $ref = 'دفترُ المتطلّباتِ الحاكم';
            $basis = substr($spec, 7);
        }
        /* مجسٌّ مُحكمٌ لم يُصِب هدفَه ⇒ يسقط للفحوصِ العامّةِ ثم UNPROPAGATED — لا أخضرَ بالخريطة وحدَها */
    }

    /* ② جدولٌ حيٌّ باسمِه */
    if ($verdict === null && preg_match_all('~\b([a-z][a-z0-9_]{3,})\b~i', $hay, $m)) {
        foreach (array_unique(array_map('strtolower', $m[1])) as $tok) {
            if (isset($tables[$tok])) {
                $n = (int) $conn->query('SELECT COUNT(*) FROM `' . $tok . '`')->fetch_row()[0];
                $verdict = 'RUNTIME_PRESENT'; $kind = 'TABLE_PROBE'; $ref = $tok . ' (' . $n . ' صفًّا)';
                $basis = 'أثرُ القرارِ المسمّى جدولٌ حيٌّ في المخطَّطِ الجاري';
                break;
            }
        }
        /* ② محرّكٌ/ملفٌّ حيّ */
        if ($verdict === null) {
            foreach (array_unique(array_map('strtolower', $m[1])) as $tok) {
                if (isset($engineFiles[$tok])) {
                    $verdict = 'RUNTIME_PRESENT'; $kind = 'ENGINE_FILE'; $ref = $engineFiles[$tok];
                    $basis = 'أثرُ القرارِ المسمّى ملفُّ محرّكٍ حيٌّ على القرص';
                    break;
                }
            }
        }
        /* ②ب مركّباتُ الأسماء (CamelCase مثل StockMoveService) */
        if ($verdict === null && preg_match_all('~\b([A-Z][A-Za-z0-9]{4,})\b~', $hay, $mm)) {
            foreach (array_unique($mm[1]) as $tok) {
                $lk = strtolower($tok);
                if (isset($engineFiles[$lk])) {
                    $verdict = 'RUNTIME_PRESENT'; $kind = 'ENGINE_FILE'; $ref = $engineFiles[$lk];
                    $basis = 'أثرُ القرارِ المسمّى صنفُ خدمةٍ حيٌّ على القرص';
                    break;
                }
            }
        }
    }

    /* ③ مقيَّدٌ في دفترِ المتطلّباتِ بحالتِه */
    if ($verdict === null) {
        $st2 = $conn->prepare("SELECT COUNT(*), SUM(amd01_state = 'EVIDENCE_CLOSED') FROM repair01_requirements
            WHERE state_evidence LIKE CONCAT('%', ?, '%') OR proof_contract LIKE CONCAT('%', ?, '%')");
        $st2->bind_param('ss', $id, $id);
        $st2->execute(); $rr = $st2->get_result()->fetch_row(); $st2->close();
        if ((int) $rr[0] > 0) {
            $closed = (int) $rr[1];
            $verdict = $closed > 0 ? 'RUNTIME_PRESENT' : 'TARGET_PROPAGATED_BUILD_PENDING';
            $kind = 'REQ_LEDGER'; $ref = $rr[0] . ' متطلّبًا يستشهد بالقرار (' . $closed . ' مغلقًا بالدليل)';
            $basis = 'القرارُ مُسقَطٌ في دفترِ المتطلّباتِ الحاكمِ للبناء';
        }
    }

    if ($verdict === null) { $verdict = 'UNPROPAGATED'; $kind = 'NONE'; $ref = '—'; $basis = 'لا مجسَّ آليًّا — يُحكم يدويًّا في الجولة'; $unprop[] = $id . ' · ' . mb_substr($d['owner_decision'], 0, 80); }
    $counts[$verdict] = ($counts[$verdict] ?? 0) + 1;
    if ($APPLY) {
        $ins->bind_param('ssssss', $id, $verdict, $kind, $ref, $basis, $SNAP);
        $ins->execute();
    }
}

echo "══ إسقاطُ القراراتِ المعتمدة (114) " . ($APPLY ? '(كتابة)' : '(قياس)') . " ══\n";
foreach ($counts as $v => $n) { printf("  %-34s %d\n", $v, $n); }
if ($unprop) {
    echo "\n◆ بلا مجسٍّ آليٍّ — تُحكم يدويًّا:\n";
    foreach ($unprop as $u2) { echo "    · $u2\n"; }
}
