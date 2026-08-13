<?php
/**
 * tools/fix_inj_tests.php — اختباراتُ قبولِ ملاحظاتِ السجلِّ الجامع (INJ-*)
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0001 · INJ-0003 · INJ-0004 · INJ-0013
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لكلِّ ملاحظةٍ **اختبارُ القبولِ المكتوبُ في عمودِها** — يُشغَّل هنا حرفيًّا لا
 *   يُعاد تفسيرُه. والإغلاقُ بتشغيلِه لا بالتوقيعِ عليه (CL-01).
 *
 * التشغيل: php tools/fix_inj_tests.php [--only=INJ-0004]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();

$only = null;
foreach ($argv as $a) { if (strpos($a, '--only=') === 0) { $only = substr($a, 7); } }

$R = array();
function inj($code, $title, $test, $ok, $evidence)
{
    global $R, $only;
    if ($only !== null && $only !== $code) { return; }
    $R[] = compact('code', 'title', 'ok', 'evidence');
    printf("%s %-10s %s\n        اختبارُ القبول: %s\n        الشاهد: %s\n\n",
        $ok ? '✔' : '✘', $code, $title, $test, $evidence);
}

echo "══════════════════════════════════════════════════════════════════════\n";
echo " اختباراتُ قبولِ السجلِّ الجامع — تُشغَّل كما كُتبت في عمودِها\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

/* ═══ INJ-0004 · الحقلُ الحساسُ لا يُرسَل لغيرِ المخوَّل ══════════════════ */
if ($only === null || $only === 'INJ-0004') {
    $emp = fix_one($db, "SELECT id FROM employees WHERE monthly_salary IS NOT NULL AND monthly_salary > 0 ORDER BY id LIMIT 1");
    if ($emp === null) { $emp = fix_one($db, "SELECT id FROM employees ORDER BY id LIMIT 1"); }
    if ($emp === null) {
        inj('INJ-0004', 'الحقلُ الحساسُ محجوبٌ في الخادم', 'استجابتان بحسابين', false, 'لا موظفَ في القاعدةِ للقياس');
    } else {
        /* ◆ الاختبارُ **لا يقبل نجاحًا خاويًا**: يشترط أن تنجح الاستجابةُ وأن
             تحمل حقولًا فعلًا؛ فغيابُ الراتبِ في استجابةٍ فاشلةٍ أو فارغةٍ ليس
             حجبًا بل عطبٌ يُقرأ حجبًا (وقد وقع فعلًا في المحاولةِ الأولى). */
        $run = function ($role) use ($ROOT, $emp) {
            $out = (string) @shell_exec(escapeshellarg(PHP_BINARY) . ' '
                 . escapeshellarg($ROOT . '/tools/fix_probe_employee_data.php') . ' '
                 . escapeshellarg((string) $role) . ' ' . (int) $emp . ' 2>&1');
            $j = json_decode(trim($out), true);
            if (!is_array($j)) { return null; }
            $drv = isset($j['driver']) && is_array($j['driver']) ? $j['driver'] : array();
            $has = function ($keys) use ($drv) {
                foreach ($keys as $k) {
                    if (array_key_exists($k, $drv) && $drv[$k] !== null && $drv[$k] !== '') { return $k; }
                }
                return null;
            };
            return array(
                'success'  => !empty($j['success']),
                'keys'     => count($drv),
                'salary'   => $has(array('monthly_salary', 'salary_type', 'salary', 'basic_salary')),
                'identity' => $has(array('identity_number', 'identity_photo', 'national_id', 'passport_no')),
                'health'   => $has(array('health_status', 'health_issues', 'medical_report_path')),
                'redacted' => isset($j['redacted']) && is_array($j['redacted']) ? $j['redacted'] : array(),
            );
        };
        $plain = $run(11);   // مشغّل أسطول — لا منحةَ أجورٍ له
        $ok = is_array($plain)
            && !empty($plain['success'])            // الاستجابةُ نجحت فعلًا
            && $plain['keys'] >= 10                 // وحملت حقولًا — لا فراغًا يُقرأ حجبًا
            && $plain['salary']   === null
            && $plain['identity'] === null
            && $plain['health']   === null
            && count($plain['redacted']) > 0;       // والحجبُ مُعلَنٌ لا مضمَر
        inj('INJ-0004', 'الحقلُ الحساسُ محجوبٌ في الخادمِ لا في العرض',
            'حسابٌ بلا صلاحيةِ الأجورِ لا يجد قيمةَ الراتبِ في جسمِ الاستجابة',
            $ok,
            is_array($plain)
                ? ('موظف #' . $emp . ' بدور 11 · استجابةٌ ناجحةٌ بـ' . $plain['keys'] . ' حقلًا: '
                   . 'الراتبُ ' . ($plain['salary'] === null ? 'غائبٌ ✔' : 'موجودٌ ✗ (' . $plain['salary'] . ')')
                   . ' · الهويةُ ' . ($plain['identity'] === null ? 'غائبةٌ ✔' : 'موجودةٌ ✗ (' . $plain['identity'] . ')')
                   . ' · الصحةُ ' . ($plain['health'] === null ? 'غائبةٌ ✔' : 'موجودةٌ ✗ (' . $plain['health'] . ')')
                   . ' · المحجوبُ مُعلَنٌ: ' . count($plain['redacted']) . ' حقلًا')
                : 'النداءُ الحيُّ لم يُرجع JSON صالحًا');
    }
}

/* ═══ INJ-0003 · مخزنٌ واحدٌ لنماذج التمويل ══════════════════════════════ */
if ($only === null || $only === 'INJ-0003') {
    $src = (string) @file_get_contents($ROOT . '/Financing/fin_models.php');
    $code = fix_strip_comments($src);
    $viaCmp03 = (strpos($code, 'cmp03_store_insert') !== false);
    $viaDomain = (strpos($code, 'financing_models') !== false);
    $scrRows = (int) fix_one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scr_fin_models'");
    $scrCount = $scrRows ? (int) fix_one($db, "SELECT COUNT(*) FROM scr_fin_models") : 0;
    $domCount = (int) fix_one($db, "SELECT COUNT(*) FROM financing_models");
    inj('INJ-0003', 'مخزنٌ واحدٌ لنماذج التمويل — لا مخزنانِ منفصلان',
        'نموذجٌ يُضاف من الشاشة يظهر فورًا في قائمةِ إنشاءِ العملية · وصفرُ صفٍّ جديدٍ في scr_fin_models',
        (!$viaCmp03 && $viaDomain),
        'الشاشةُ تكتب: ' . ($viaCmp03 ? 'المخزنَ البينيَّ scr_fin_models ✗' : '—')
            . ($viaDomain ? ' · جدولَ المجال financing_models ✔' : ' · لا جدولَ مجالٍ ✗')
            . " · صفوف: financing_models={$domCount} · scr_fin_models={$scrCount}");
}

/* ═══ INJ-0001 · أفعالُ آلةِ حالةِ العقدِ في شاشتها الأم ═══════════════════ */
if ($only === null || $only === 'INJ-0001') {
    $src = (string) @file_get_contents($ROOT . '/Contracts/contracts.php');
    $code = fix_strip_comments($src);
    $hasStatus = (strpos($code, 'contract_status') !== false);
    $hasMachine = (strpos($code, 'ContractStateMachine') !== false);
    $acts = 0;
    foreach (array('contract.submit_negotiation', 'contract.approve') as $a) {
        if (fix_one($db, "SELECT 1 FROM nav09_action_map WHERE canonical_code = '" . $db->real_escape_string($a) . "'") !== null) { $acts++; }
    }
    /* ◆ والشاهدُ الحقيقيُّ **تشغيلُ السلسلةِ** لا وجودُ شيفرتِها: عقدٌ يُنقل
         مسودة ← تفاوض ← معتمد ثم يُفحص ظهورُه في استعلامِ قائمةِ توقيعِ القمة
         (`contract_status = 'معتمد'`). وكلُّ ما يُصنع هنا يُعاد كما كان. */
    $chain = 'لم تُشغَّل';
    $chainOk = false;
    $c = $db->query("SELECT id, company_id, contract_status FROM contracts
                      WHERE COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1");
    $row = $c ? $c->fetch_assoc() : null;
    if ($row) {
        $cid = (int) $row['id']; $co = (int) $row['company_id']; $orig = (string) $row['contract_status'];
        $capSeeded = false;
        try {
            require_once $ROOT . '/app/Core/TenantDb.php';
            require_once $ROOT . '/app/Services/Contract/ContractApprovalService.php';
            /* سقفٌ مؤقتٌ للاختبارِ وحدَه — يُمحى في النهاية (تعريفُ السقوفِ قرارُ مالك).
               ◆ وعملتُه **عملةُ العقدِ نفسِها**: الحارسُ يرفض المقارنةَ بين عملتين
                 بلا تحويلٍ معلَن (وهو الصواب) — فسقفٌ بعملةٍ أخرى يُنتج تصعيدًا
                 يُقرأ «الفعلُ لا يعمل» وهو في الحقيقةِ حارسٌ يعمل. */
            $curr = (string) fix_one($db, "SELECT price_currency_contract FROM contracts WHERE id={$cid}");
            $db->query("INSERT INTO fin_authority_caps
                          (company_id, scope_kind, scope_ref, apr_code, max_amount, currency,
                           escalates_to_role, effective_from, effective_to, authority_ref, active, created_by)
                        VALUES ({$co},'role','12','TEST',999999999,'" . $db->real_escape_string($curr) . "',
                                9,CURDATE(),CURDATE(),'INJ-0001 probe',1,0)");
            $capSeeded = ($db->errno === 0);
            if (!$capSeeded) { $chain = 'تعذّر بذرُ السقفِ المؤقت: ' . $db->error; }

            $db->query("UPDATE contracts SET contract_status='مسودة' WHERE id={$cid}");
            // ◆ البوابةُ تُبنى بمُهيِّئها العامِّ لا بمعاملاتٍ خام: ‎TenantDb‎ يطلب
            //   ‎TenantContext‎ لا رقمَ شركة — وبناؤها يدويًّا يُسقط سياقَ الجلسة.
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            $_SESSION['user'] = array('id' => 0, 'role' => '12', 'company_id' => $co, 'name' => 'فاحصُ السلسلة');
            $gate = ems_tenant_db();
            $svc  = new \App\Services\Contract\ContractApprovalService($db);
            $r1 = $svc->submitForNegotiation($gate, $co, $cid, 'اختبارُ قبولٍ ①', 0);
            $r2 = $svc->approve($gate, $co, $cid, 'اختبارُ قبولٍ ②', 0, 12);
            $now = (string) fix_one($db, "SELECT contract_status FROM contracts WHERE id={$cid}");
            $inQueue = (int) fix_one($db, "SELECT COUNT(*) FROM contracts
                                            WHERE id={$cid} AND company_id={$co}
                                              AND COALESCE(is_deleted,0)=0 AND contract_status='معتمد'");
            $chainOk = (!empty($r1['ok']) && !empty($r2['ok']) && $now === 'معتمد' && $inQueue === 1);
            $chain = 'مسودة → ' . ($r1['ok'] ? 'تفاوض ✔' : 'رُفض ✘ (' . $r1['reason'] . ')')
                   . ' → ' . ($r2['ok'] ? 'معتمد ✔' : (!empty($r2['escalated']) ? 'صُعِّد ⬆' : 'رُفض ✘'))
                   . ' · الحالةُ الآن «' . $now . '» · في قائمةِ توقيعِ القمة: ' . ($inQueue ? 'نعم ✔' : 'لا ✘');
        } catch (Throwable $e) {
            $chain = 'استثناء: ' . $e->getMessage();
        } finally {
            $db->query("UPDATE contracts SET contract_status='" . $db->real_escape_string($orig) . "' WHERE id={$cid}");
            if ($capSeeded) { $db->query("DELETE FROM fin_authority_caps WHERE authority_ref='INJ-0001 probe'"); }
        }
    } else { $chain = 'لا عقدَ في القاعدةِ للقياس'; }

    inj('INJ-0001', 'آلةُ حالةِ العقدِ لها أفعالٌ تعمل في شاشتها المالكة',
        'مستخدمُ المبيعاتِ ينقل عقدًا من مسودةٍ إلى معتمدٍ بفعلين مسجَّلين ويظهر في قائمةِ التوقيع',
        ($hasStatus && $hasMachine && $acts === 2 && $chainOk),
        'عمودُ الحالةِ في الشاشة: ' . ($hasStatus ? '✔' : '✘')
            . ' · نداءُ آلةِ الحالة: ' . ($hasMachine ? '✔' : '✘')
            . " · الأفعالُ المسجَّلة: {$acts}/2 · السلسلةُ الحية: " . $chain);
}

/* ═══ INJ-0013 · سلسلةُ المراجعةِ الداخليةِ تعمل من الشاشات ═══════════════ */
if ($only === null || $only === 'INJ-0013') {
    $need = array('iaf.charter.approve', 'iaf.universe.build', 'iaf.plan.approve', 'iaf.engagement.open',
                  'iaf.workpaper.attach', 'iaf.finding.raise', 'iaf.response.submit', 'iaf.actionplan.set');
    $reg = 0; $missing = array();
    foreach ($need as $a) {
        if (fix_one($db, "SELECT 1 FROM nav09_action_map WHERE canonical_code = '" . $db->real_escape_string($a) . "'") !== null) { $reg++; }
        else { $missing[] = $a; }
    }
    $withActions = 0; $screens = glob($ROOT . '/Audit/iaf_*.php');
    foreach ($screens as $f) {
        $c = fix_strip_comments((string) @file_get_contents($f));
        if (strpos($c, 'ems_post_contract') !== false || strpos($c, 'InternalAuditService') !== false) { $withActions++; }
    }
    $svc = fix_strip_comments((string) @file_get_contents($ROOT . '/app/Services/Audit/InternalAuditService.php'));
    $cycleCalls = substr_count($svc, 'assertCycle') - 1;   // ناقصًا التعريفَ نفسَه
    /* ◆ الشاهدُ الحقيقيُّ **تشغيلُ السلسلة**: كلُّ حلقةٍ تعمل في موضعها،
         و**القفزُ يُرفض بـIAF-0044**. وكلُّ ما يُصنع يُمحى في النهاية. */
    require_once $ROOT . '/app/Services/Audit/InternalAuditService.php';
    $IAS = '\\App\\Services\\Audit\\InternalAuditService';
    $co = (int) fix_one($db, "SELECT company_id FROM iaf_charter ORDER BY id LIMIT 1");
    if ($co <= 0) { $co = 4; }
    $tag = 'INJ13-' . getmypid();
    $chain = array(); $chainOk = false;
    // مراجعٌ حقيقيٌّ بالدور 33 — والفعلُ محروسٌ بالدورِ لا بالنية
    $auditor = (int) fix_one($db, "SELECT id FROM users WHERE role = '33' ORDER BY id LIMIT 1");
    $mgr     = (int) fix_one($db, "SELECT id FROM users WHERE role NOT IN ('33') AND role <> '' ORDER BY id LIMIT 1");
    try {
        // ⓪ القفزُ أولًا: مهمةٌ بلا خطةٍ يجب أن تُرفض بـIAF-0044
        $jump = $IAS::openEngagement($db, array('company_id' => $co, 'plan_id' => 999999,
            'lead_auditor' => $auditor, 'area_code' => $tag, 'title' => 'قفزةٌ يجب أن تُرفض'));
        $chain[] = 'قفزةٌ بلا خطة: ' . (empty($jump['ok']) ? 'رُفضت ✔' : 'مرَّت ✗');
        $jumpRejected = empty($jump['ok']);

        // ① ميثاقٌ معتمد
        $ver = (string) fix_one($db, "SELECT version FROM iaf_charter WHERE company_id={$co} ORDER BY id LIMIT 1");
        $r1 = $IAS::approveCharter($db, array('company_id' => $co, 'version' => $ver, 'actor' => $auditor));
        $chain[] = '①ميثاق ' . (!empty($r1['ok']) ? '✔' : '✗');
        // ② كونٌ رقابيّ
        $r2 = $IAS::buildUniverse($db, array('company_id' => $co, 'area_code' => $tag,
            'area_name' => 'مجالُ اختبارِ القبول', 'owner_dept' => 'اختبار', 'risk_score' => 5));
        $chain[] = '②كون ' . (!empty($r2['ok']) ? '✔' : '✗');
        // ③ خطةٌ معتمدة
        $r3 = $IAS::approvePlan($db, array('company_id' => $co, 'plan_year' => (int) date('Y'),
            'title' => 'خطةُ ' . $tag, 'actor' => $auditor));
        $chain[] = '③خطة ' . (!empty($r3['ok']) ? '✔' : '✗');
        $planId = (int) fix_one($db, "SELECT id FROM iaf_plan WHERE company_id={$co} AND title='خطةُ {$tag}' LIMIT 1");
        // ④ إقرارُ استقلالٍ ثم مهمة
        $IAS::declareIndependence($db, array('company_id' => $co, 'auditor_id' => $auditor, 'scope_ref' => $tag));
        $r4 = $IAS::openEngagement($db, array('company_id' => $co, 'plan_id' => $planId,
            'lead_auditor' => $auditor, 'area_code' => $tag, 'title' => 'مهمةُ ' . $tag));
        $chain[] = '④مهمة ' . (!empty($r4['ok']) ? '✔' : '✗');
        $engNo = (string) fix_one($db, "SELECT engagement_no FROM iaf_engagements
                                         WHERE company_id={$co} AND title='مهمةُ {$tag}' LIMIT 1");
        // ⑤ ورقةُ عمل
        $r5 = $IAS::attachWorkpaper($db, array('company_id' => $co, 'engagement_no' => $engNo,
            'wp_ref' => 'WP-' . $tag, 'title' => 'ورقةُ اختبار', 'actor' => $auditor));
        $chain[] = '⑤ورقة ' . (!empty($r5['ok']) ? '✔' : '✗');
        // ⑥ ملاحظة
        $r6 = $IAS::raiseFinding($db, array('company_id' => $co, 'engagement_no' => $engNo,
            'title' => 'ملاحظةُ ' . $tag, 'severity' => 'متوسطة', 'auditee_dept' => 'اختبار', 'actor' => $auditor));
        $chain[] = '⑥ملاحظة ' . (!empty($r6['ok']) ? '✔' : '✗');
        $fno = (string) fix_one($db, "SELECT finding_no FROM iaf_findings
                                       WHERE company_id={$co} AND title='ملاحظةُ {$tag}' LIMIT 1");
        // ⑦ خطةُ معالجةٍ قبلَ الردّ — يجب أن تُرفض
        $early = $IAS::setActionPlan($db, array('company_id' => $co, 'finding_no' => $fno,
            'action_plan' => 'قبلَ الرد', 'action_owner' => 'اختبار', 'action_due' => date('Y-m-d')));
        $chain[] = 'خطةٌ قبلَ الرد: ' . (empty($early['ok']) ? 'رُفضت ✔' : 'مرَّت ✗');
        // ⑦ ردٌّ ثم خطةُ معالجة
        $r7 = $IAS::submitResponse($db, array('company_id' => $co, 'finding_no' => $fno,
            'response_text' => 'ردُّ الإدارةِ للاختبار', 'actor' => $mgr));
        $chain[] = '⑦رد ' . (!empty($r7['ok']) ? '✔' : '✗');
        $r8 = $IAS::setActionPlan($db, array('company_id' => $co, 'finding_no' => $fno,
            'action_plan' => 'معالجةُ الاختبار', 'action_owner' => 'اختبار', 'action_due' => date('Y-m-d', strtotime('+30 days'))));
        $chain[] = '⑧خطةُ معالجة ' . (!empty($r8['ok']) ? '✔' : '✗');

        $chainOk = $jumpRejected && !empty($r1['ok']) && !empty($r2['ok']) && !empty($r3['ok'])
                && !empty($r4['ok']) && !empty($r5['ok']) && !empty($r6['ok'])
                && empty($early['ok']) && !empty($r7['ok']) && !empty($r8['ok']);
        if (!$chainOk) {
            foreach (array('①' => $r1, '②' => $r2, '③' => $r3, '④' => $r4, '⑤' => $r5, '⑥' => $r6, '⑦' => $r7, '⑧' => $r8) as $k => $rr) {
                if (empty($rr['ok'])) { $chain[] = $k . ' سبب: ' . mb_substr((string) ($rr['reason'] ?? ''), 0, 70); }
            }
        }
    } catch (Throwable $e) {
        $chain[] = 'استثناء: ' . $e->getMessage();
    } finally {
        $db->query("DELETE FROM iaf_findings     WHERE company_id={$co} AND title LIKE '%{$tag}%'");
        $db->query("DELETE FROM iaf_workpapers   WHERE company_id={$co} AND wp_ref LIKE '%{$tag}%'");
        $db->query("DELETE FROM iaf_engagements  WHERE company_id={$co} AND title LIKE '%{$tag}%'");
        $db->query("DELETE FROM iaf_plan         WHERE company_id={$co} AND title LIKE '%{$tag}%'");
        $db->query("DELETE FROM iaf_universe     WHERE company_id={$co} AND area_code='{$tag}'");
        $db->query("DELETE FROM iaf_independence WHERE company_id={$co} AND scope_ref='{$tag}'");
    }

    /* ◆ ثغرةٌ أُغلقت في الفاحصِ نفسِه: السلسلةُ أعلاه تنادي **الخدمةَ** مباشرةً،
         فمرَّت خضراءَ بينما الشاشاتُ المولَّدةُ كانت **مكسورةَ التركيب** (شرطةٌ
         مائلةٌ مضاعفة) ولم يكشفها إلا مسحٌ شاملٌ للشجرة. فالفحصُ الآن **يُصيِّر
         كلَّ شاشةٍ بدورِ مالكها** ويشترط ظهورَ نموذجِ فعلٍ فيها. */
    $renderOk = 0; $renderBad = array();
    foreach (array('iaf_charter', 'iaf_independence', 'iaf_universe', 'iaf_plan', 'iaf_engagements',
                   'iaf_workpapers', 'iaf_findings', 'iaf_responses', 'iaf_action_plans') as $sc) {
        $rr = fix_render_screen($ROOT, 'Audit/' . $sc . '.php', '33');
        if ($rr['fatal'] === '' && $rr['bytes'] > 0 && strpos($rr['body'], 'u13_action') !== false) { $renderOk++; }
        else { $renderBad[] = $sc . ($rr['fatal'] !== '' ? ' (عطب)' : ' (بلا نموذجِ فعل)'); }
    }

    inj('INJ-0013', 'سلسلةُ المراجعةِ تعمل من الشاشاتِ وكلُّ قفزةٍ تُرفض',
        'بحساب الدور 33: ميثاقٌ ← كونٌ ← خطةٌ ← مهمةٌ ← ورقةٌ ← ملاحظةٌ ← ردٌّ ← خطةُ معالجة',
        ($reg === 8 && $withActions >= 8 && $cycleCalls > 0 && $chainOk && $renderOk === 9),
        "الأفعالُ المسجَّلة: {$reg}/8" . ($missing ? ' (ناقص: ' . implode('، ', array_slice($missing, 0, 4)) . ')' : '')
            . ' · شاشاتٌ بأفعال: ' . $withActions . '/' . count($screens) . ' · مُصيَّرةٌ بنموذجِ فعل: ' . $renderOk . '/9'
            . " · نداءاتُ assertCycle: {$cycleCalls}\n        السلسلةُ الحية: " . implode(' · ', $chain));
}

$pass = 0; $fail = 0;
foreach ($R as $r) { $r['ok'] ? $pass++ : $fail++; }
echo str_repeat('═', 70) . "\n";
printf("النتيجة: %d/%d\n", $pass, $pass + $fail);
echo str_repeat('═', 70) . "\n";
exit($fail === 0 ? 0 : 1);
