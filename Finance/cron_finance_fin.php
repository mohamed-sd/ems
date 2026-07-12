<?php
/**
 * Finance/cron_finance_fin.php — محرّك المراقبة المجدوَل (§4).
 * مهام: تحديث حالة الذمم المتأخرة (overdue) بمقارنة تاريخ الاستحقاق باليوم.
 * يُشغَّل عبر جدولة النظام (CLI) أو GET بمفتاح. لا يلمس أي جدول قائم.
 */
$IS_CLI = (PHP_SAPI === 'cli');
require __DIR__ . '/../config.php';

// حارس التشغيل عبر المتصفح: ?key=... يُطابَق مع FINANCE_CRON_KEY من .env (ADR-04).
// fail-closed: مفتاح غير مضبوط في .env = لا مسار ويب إطلاقًا (CLI لا يتأثر).
if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('FINANCE_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}

// F3 · القناة الخامسة forSystem (عقد §11) — حلقة شركاتٍ ببوابةٍ معزولةٍ لكل دورة،
// تُحقن في fin_helpers عبر fin_gate_override (نمط cron_transfer). بلغنا هذه النقطة =
// CLI أو المفتاح تحقق (الحارس أعلاه fail-closed) ⇒ تفويض النظام قائم.
require_once __DIR__ . '/../app/Core/TenantGateException.php';
require_once __DIR__ . '/../app/Core/TenantRegistry.php';
require_once __DIR__ . '/../app/Core/TenantContext.php';
require_once __DIR__ . '/../app/Core/TenantDb.php';
require_once __DIR__ . '/fin_helpers.php';
$SYS_AUTH = true;

$today = date('Y-m-d');
$n_overdue = $n_collected = $n_fund = $n_bud = $n_ntf = 0;

// تعداد الشركات المعنيّة: اتحاد الشركات ذات ذمم/أقساط/موازنات/أحداث — عبر سياق نظامٍ
// super + forAllTenants المسجَّلة (لا خام). كل تحديثٍ عالميٍّ سابق صار دورةً معزولة بشركة.
$enumGate = new \App\Core\TenantDb($conn,
    \App\Core\TenantContext::forSystem(0, 0, defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1', $SYS_AUTH),
    false, 'enforce');
$company_ids = array();
foreach (array('fin_receivables', 'fin_funding_schedules', 'fin_budgets', 'fin_financial_events') as $t) {
    foreach ($enumGate->forAllTenants('finance cron company enumeration')
                 ->select($t, array('columns' => array('company_id'), 'includeDeleted' => true)) as $row) {
        $company_ids[intval($row['company_id'])] = true;
    }
}

foreach (array_keys($company_ids) as $cid) {
    // بوابة دورةٍ معزولة بشركةٍ واحدة صريحة — تُحقن في fin_helpers
    $cycleGate = new \App\Core\TenantDb($conn,
        \App\Core\TenantContext::forSystem($cid, 0, '', $SYS_AUTH), false, 'enforce');
    fin_gate_override($cycleGate);

    // 1) ذمم متأخرة: لها متبقٍّ وتجاوزت الاستحقاق ولم تُحصّل بالكامل
    $n_overdue += $cycleGate->update('fin_receivables', array('state' => 'overdue'), array(),
        "COALESCE(is_deleted,0)=0 AND outstanding > 0 AND due_date IS NOT NULL AND due_date < ? AND state IN('open','partial')",
        array($today));

    // 2) ذمم عادت للسداد الكامل تُعلَّم collected
    $n_collected += $cycleGate->update('fin_receivables', array('state' => 'collected'), array(),
        "COALESCE(is_deleted,0)=0 AND outstanding <= 0 AND state<>'collected'");

    // 3) أقساط تمويل متأخرة (إصلاح #6): مستحقة غير مسدَّدة تجاوزت تاريخها
    $n_fund += $cycleGate->update('fin_funding_schedules', array('state' => 'overdue'), array(),
        "state IN('due','partial') AND paid_amount < total_due AND due_date < ?",
        array($today));

    // 4) (فجوة 3) الانحراف المستمر: إعادة احتساب فعلي الموازنة (helper مُحقَّن بالدورة)
    $n_bud += fin_recalc_budget_actuals($conn, $cid);

    // 5) (فجوة 4) تنبيهات المراقبة المستمرة (بمنع تكرار يومي داخل fin_notify)
    // ذمم متأخرة → الخزينة والمدير المالي
    $r = $cycleGate->scopedQuery(array('scope' => array('r' => 'fin_receivables')),
        "SELECT COUNT(*) n, COALESCE(SUM(r.outstanding),0) t FROM fin_receivables r WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.state='overdue' AND r.outstanding>0");
    if ($r && intval($r[0]['n']) > 0) {
        fin_notify($conn, $cid, 'treasurer', 'ذمم متأخرة: ' . $r[0]['n'] . ' فاتورة بمبلغ ' . number_format((float)$r[0]['t'], 0), 'dues_fin.php'); $n_ntf++;
        fin_notify($conn, $cid, 'finance_manager', 'ذمم متأخرة: ' . $r[0]['n'] . ' فاتورة بمبلغ ' . number_format((float)$r[0]['t'], 0), 'dues_fin.php'); $n_ntf++;
    }
    // أقساط تمويل خلال 7 أيام → المدير المالي
    $r = $cycleGate->scopedQuery(array('scope' => array('f' => 'fin_funding_schedules')),
        "SELECT COUNT(*) n, COALESCE(SUM(f.total_due-f.paid_amount),0) t FROM fin_funding_schedules f WHERE {TENANT_SCOPE} AND f.state<>'paid' AND f.due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)",
        array($today, $today));
    if ($r && intval($r[0]['n']) > 0) {
        fin_notify($conn, $cid, 'finance_manager', 'أقساط تمويل تستحق خلال 7 أيام: ' . $r[0]['n'] . ' قسط بمبلغ ' . number_format((float)$r[0]['t'], 0), 'funding_fin.php'); $n_ntf++;
    }
    // انحرافات فوق 10% → المدير المالي (INNER JOIN budgets → LEFT + b.id IS NOT NULL)
    $r = $cycleGate->scopedQuery(
        array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
        "SELECT COUNT(*) n FROM fin_budget_lines l LEFT JOIN fin_budgets b ON b.id=l.budget_id WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND b.state IN('approved','active') AND COALESCE(b.is_deleted,0)=0 AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct) > 10");
    if ($r && intval($r[0]['n']) > 0) {
        fin_notify($conn, $cid, 'finance_manager', 'انحرافات موازنة فوق 10%: ' . $r[0]['n'] . ' بند', 'budget_form_fin.php'); $n_ntf++;
    }

    fin_gate_override(null); // نهاية الدورة — لا بوابة معلّقة بين الشركات
}

$out = "[finance-cron $today] recv_overdue=$n_overdue recv_collected=$n_collected funding_overdue=$n_fund budget_lines_fed=$n_bud notifications=$n_ntf\n";
echo $out;
