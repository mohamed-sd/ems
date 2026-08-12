<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * N-02 — اختبار قبول سجل التدقيق بقيم قبل/بعد
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/audit_trail_test.php
 *
 * ما يُثبته:
 *   ① الموصل ems_audit_change: يكتب **المتغيّرَ فقط** بقيم قبل/بعد JSON —
 *      واللاتغييرُ لا يكتب صفَّ ضوضاء.
 *   ② العقود (E2E): انتقالُ حالةٍ حقيقيٌّ عبر ContractStateMachine يترك أثرَه
 *      في activity_logs بقيمَي قبل/بعد — والتعليقُ والاستئنافُ كذلك.
 *   ③ بقيةُ مواضع الوصل الخمسة (القيود ترحيلًا وحذفًا · التسويات رفعًا
 *      واعتمادًا · الصلاحيات بشاشتيها): وجودُ الوصل مُثبَتٌ مصدريًّا —
 *      وصحةُ الكتابة نفسُها مثبتةٌ في ①.
 *
 * البذرُ معزول: يستعيد حالةَ العقد حرفيًّا ويكنس صفوفَ تدقيقه (§3).
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: FIXA-0010-ب · FIXA-0010-ج
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
require_once dirname(__DIR__) . '/includes/audit_trail.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Services\Contract\ContractStateMachine as CSM;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 1;
$MARK = 'N2AUD_' . getmypid();
$auditFloor = intval($conn->query("SELECT COALESCE(MAX(id),0) m FROM activity_logs")->fetch_assoc()['m']);

$teardown = function () use ($conn, $MARK, &$auditFloor) {
    // صفوفُ تدقيق هذا الاختبار وحدَها: بعد الأرضية وبوسمنا أو بوحداتنا المفحوصة
    $conn->query("DELETE FROM activity_logs WHERE id > {$auditFloor}
                    AND (screen_name = '{$MARK}' OR module_name IN ('contracts','settlements','journal','permissions'))");
};
register_shutdown_function($teardown);

fwrite(STDOUT, "\n══ N-02 — سجلُّ التدقيق بقيم قبل/بعد ══\n");

// ═══ ① الموصل ═══
head('① ems_audit_change — diff بقيم قبل/بعد ولا صفَّ للاتغيير');
$okc = ems_audit_change($conn, 'contracts', $MARK, 'update', 991,
    array('status' => 'قديم', 'amount' => 100, 'same' => 'x'),
    array('status' => 'جديد', 'amount' => 150, 'same' => 'x'),
    array('company_id' => $CO, 'user_id' => $ACTOR, 'note' => 'اختبار'));
check($okc === true, 'الكتابة نجحت');
$row = $conn->query("SELECT * FROM activity_logs WHERE screen_name='{$MARK}' ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($row !== null && intval($row['record_id']) === 991, 'الصفُّ كُتب بمعرّف السجل');
$o = json_decode((string) $row['old_value'], true);
$n = json_decode((string) $row['new_value'], true);
check(is_array($o) && $o['status'] === 'قديم' && (string) $o['amount'] === '100', 'قيمُ «قبل» محمولة: ' . $row['old_value']);
check(is_array($n) && $n['status'] === 'جديد' && (string) $n['amount'] === '150', 'قيمُ «بعد» محمولة');
check(!isset($o['same']) && !isset($n['same']), 'وغيرُ المتغيّر لا يُسجَّل — diff لا لقطة');
check(isset($n['_note']) && $n['_note'] === 'اختبار', 'والسببُ محمولٌ مع «بعد»');
check(intval($row['user_id']) === $ACTOR && intval($row['company_id']) === $CO, 'ومَن ولأي شركةٍ معلومان');

$before = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE screen_name='{$MARK}'")->fetch_assoc()['c']);
ems_audit_change($conn, 'contracts', $MARK, 'update', 991,
    array('status' => 'ثابت'), array('status' => 'ثابت'));
$after = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE screen_name='{$MARK}'")->fetch_assoc()['c']);
check($after === $before, 'اللاتغييرُ لا يكتب صفًّا — لا ضوضاء');

// ═══ ② العقود E2E ═══
head('② العقود — الانتقالُ الحقيقي يترك أثرَه بقيمَي قبل/بعد');
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));
$C = $conn->query("SELECT id, contract_status, pause_state_before, pause_date, resume_date
                     FROM contracts WHERE company_id={$CO} AND contract_status='نافذ'
                     ORDER BY id LIMIT 1")->fetch_assoc();
if (!$C) { bad('لا عقدَ نافذًا للاختبار'); }
else {
    $CID = (int) $C['id'];
    $restore = function () use ($conn, $CID, $C) {
        $pb = ($C['pause_state_before'] === null) ? 'NULL' : "'" . $conn->real_escape_string($C['pause_state_before']) . "'";
        $pd = ($C['pause_date'] === null) ? 'NULL' : "'" . $C['pause_date'] . "'";
        $rd = ($C['resume_date'] === null) ? 'NULL' : "'" . $C['resume_date'] . "'";
        $conn->query("UPDATE contracts SET contract_status='" . $conn->real_escape_string($C['contract_status'])
                     . "', pause_state_before={$pb}, pause_date={$pd}, resume_date={$rd} WHERE id={$CID}");
        $conn->query("DELETE FROM ems_business_events
                       WHERE event_key='contract.state.changed' AND entity_id={$CID}
                         AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
    };
    register_shutdown_function($restore);

    $r = CSM::transition($conn, $gate, $CO, $CID, CSM::RUNNING, 'اختبار N-02', $ACTOR);
    check(!empty($r['ok']) && !empty($r['changed']), "العقد #{$CID}: نافذ ← قيد التنفيذ وقع");
    $a = $conn->query("SELECT * FROM activity_logs WHERE id > {$auditFloor}
        AND module_name='contracts' AND action_type='state_transition' AND record_id={$CID}
        ORDER BY id DESC LIMIT 1")->fetch_assoc();
    check($a !== null, 'صفُّ تدقيقٍ كُتب للانتقال');
    if ($a) {
        $o = json_decode((string) $a['old_value'], true);
        $n = json_decode((string) $a['new_value'], true);
        check($o['contract_status'] === 'نافذ' && $n['contract_status'] === 'قيد التنفيذ',
            "قبل=«{$o['contract_status']}» → بعد=«{$n['contract_status']}»");
        check(intval($a['contract_id']) === $CID, 'وخيطُ العقد محمول');
        check($n['_note'] === 'اختبار N-02', 'والسببُ مدوَّن');
    }

    $r = CSM::suspend($conn, $gate, $CO, $CID, 'تعليق اختباري', $ACTOR);
    check(!empty($r['ok']), 'والتعليق وقع');
    $c2 = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE id > {$auditFloor}
        AND module_name='contracts' AND record_id={$CID}")->fetch_assoc()['c']);
    check($c2 >= 2, "والتعليقُ مدقَّقٌ أيضًا (صفوف العقد: {$c2})");
    $r = CSM::resume($conn, $gate, $CO, $CID, 'استئناف اختباري', $ACTOR);
    check(!empty($r['ok']), 'والاستئناف وقع');
    $c3 = intval($conn->query("SELECT COUNT(*) c FROM activity_logs WHERE id > {$auditFloor}
        AND module_name='contracts' AND record_id={$CID}")->fetch_assoc()['c']);
    check($c3 >= 3, "والاستئنافُ كذلك ({$c3})");
    $restore();
}

// ═══ ③ مواضعُ الوصل الخمسة الباقية — إثباتٌ مصدري ═══
head('③ مواضعُ الوصل — القيود والتسويات والصلاحيات موصولةٌ مصدريًّا');
$sites = array(
    'journal: الترحيل'        => array('Finance/journal_form_fin.php', "ems_audit_change(\$conn, 'journal', 'journal_form_fin', 'post'"),
    'journal: حذف المسودة'    => array('Finance/journal_form_fin.php', "'soft_delete'"),
    'settlements: الرفع'      => array('app/Services/Settlement/SettlementService.php', "'state_transition'"),
    'settlements: الاعتماد'   => array('app/Services/Settlement/SettlementService.php', "'approve'"),
    'permissions: الشاشة'     => array('Settings/role_permissions.php', "ems_audit_change"),
    'permissions: الكونسول'   => array('admin/permissions/role_permissions.php', "ems_audit_change"),
);
foreach ($sites as $label => $s) {
    $src = file_get_contents(dirname(__DIR__) . '/' . $s[0]);
    check($src !== false && strpos($src, $s[1]) !== false, $label . ' موصول');
}
// وتغطيةُ الصلاحيات كاملةٌ بأفعالها الأربعة في الشاشتين
$src1 = file_get_contents(dirname(__DIR__) . '/Settings/role_permissions.php');
$src2 = file_get_contents(dirname(__DIR__) . '/admin/permissions/role_permissions.php');
foreach (array("'grant_all'", "'revoke_all'", "'revoke'") as $act) {
    check(strpos($src1, $act) !== false && strpos($src2, $act) !== false,
        'الفعل ' . trim($act, "'") . ' مدقَّقٌ في الشاشتين');
}

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
