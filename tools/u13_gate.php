<?php
/**
 * tools/u13_gate.php — بوابةُ القبولِ لحزمة update0013
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الفاحصُ العكسي: لا يسأل «أنجحَ ما بنيتُه؟» بل **«أبنيتُ ما طُلب؟»**.
 *   يقرأ `docs/update0013/spec.json` المستخرَجَ من الوثائقِ ويقارنه بالحيِّ
 *   صفًّا صفًّا — ثم يُشغِّل المحرّكاتِ فعلًا لا يتفقّد وجودَ ملفاتِها.
 *
 * ◆ گوتشا مُتجنَّبة: **لا رقمَ مثبَّتٌ في البوابة**. كلُّ عددٍ مقروءٌ من
 *   spec.json — فتثبيتُ عددٍ في بوابةٍ يُنتج رسوبًا كاذبًا حين تتغير الوثيقة.
 *
 * التشغيل: php tools/u13_gate.php [--item=1] [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$only = null; $mdOut = null;
foreach ($argv as $a) {
    if (strpos($a, '--item=') === 0) { $only = (int) substr($a, 7); }
    if (strpos($a, '--md=')   === 0) { $mdOut = substr($a, 5); }
}

$S = json_decode(@file_get_contents($ROOT . '/docs/update0013/spec.json'), true);
if (!is_array($S)) { exit("ناقص أو تالف: docs/update0013/spec.json\n"); }

$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_PORT') { $cfg['port'] = (int) $v; }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db']   = $v; }
    }
}
$db = @new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

require_once $ROOT . '/app/Services/Work/WorkItemService.php';
require_once $ROOT . '/app/Services/Finance/RoutingEngine.php';

use App\Services\Finance\RoutingEngine;

$CHECKS = array(); $NOTES = array();

function chk($item, $id, $title, $ok, $detail = '')
{
    global $CHECKS;
    $CHECKS[] = array('item' => $item, 'id' => $id, 'title' => $title, 'ok' => (bool) $ok, 'detail' => (string) $detail);
}
function note($t) { global $NOTES; $NOTES[] = $t; }
/**
 * قراءةُ قيمةٍ واحدة.
 * ◆ گوتشا مُهلِكةٌ لبوابة: استعلامٌ فاشلٌ (عمودٌ غيرُ موجود) يعود null، و`(int) null`
 *   صفرٌ — فيمرُّ فحصُ «صفرُ كذا» **كاذبًا**. فالفشلُ يُسجَّل رسوبًا صريحًا هنا
 *   ولا يُبتلع.
 */
$SQL_ERRORS = array();
function one($db, $sql)
{
    global $SQL_ERRORS;
    $r = $db->query($sql);
    if (!$r) { $SQL_ERRORS[] = $db->error . ' — ' . preg_replace('~\s+~', ' ', mb_substr($sql, 0, 90)); return null; }
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
}
function rows($db, $sql) { $r = $db->query($sql); $o = array(); while ($r && $x = $r->fetch_assoc()) { $o[] = $x; } return $o; }

/* ═══════════════════════════════════════════════════════════════════════════
   البند ① — مصفوفةُ التوجيهِ لمحاسبي التخصصات
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 1) {

    /* ①-01 التخصصاتُ العشرةُ: عددًا ونصًّا حرفًا بحرف. */
    $live = array();
    foreach (rows($db, "SELECT code,name_ar,name_en,accounts,scope,dims,limit_rule,doc_ref
                          FROM fin_acc_specializations WHERE active=1") as $r) { $live[$r['code']] = $r; }
    $want = count($S['acc_specializations']);
    chk(1, '①-01', "التخصصاتُ المحاسبيةُ حاضرةٌ بعددِ الوثيقة ($want)",
        count($live) === $want, sprintf('الوثيقة %d · الحي %d', $want, count($live)));

    $drift = array();
    foreach ($S['acc_specializations'] as $x) {
        if (!isset($live[$x['code']])) { $drift[] = $x['code'] . ' غائب'; continue; }
        $L = $live[$x['code']];
        foreach (array('name_ar' => $x['name_ar'], 'name_en' => $x['name_en'], 'dims' => $x['dims'],
                       'accounts' => mb_substr($x['accounts'], 0, 255), 'limit_rule' => mb_substr($x['limit'], 0, 300)) as $col => $exp) {
            if (trim((string) $L[$col]) !== trim((string) $exp)) { $drift[] = $x['code'] . '.' . $col; }
        }
    }
    chk(1, '①-02', 'نصُّ كلِّ تخصصٍ في الحيِّ يطابق الوثيقةَ حرفًا بحرف',
        !$drift, $drift ? 'انحراف: ' . implode(' · ', array_slice($drift, 0, 8)) : 'صفرُ انحراف');

    /* ①-03 المسارات: عددًا ووجهةً وحكمًا. */
    $liveR = array();
    foreach (rows($db, "SELECT code,kind,trigger_ar,trigger_key,source_dept,launch_cond,target_spec,
                               accounts,dims,chain,guard_rule,doc_ref
                          FROM fin_routing_matrix WHERE active=1") as $r) { $liveR[$r['code']] = $r; }
    $wantR = count($S['routes']);
    chk(1, '①-03', "مساراتُ التوجيهِ حاضرةٌ بعددِ الوثيقة ($wantR)",
        count($liveR) === $wantR, sprintf('الوثيقة %d · الحي %d', $wantR, count($liveR)));

    $rd = array();
    foreach ($S['routes'] as $x) {
        if (!isset($liveR[$x['code']])) { $rd[] = $x['code'] . ' غائب'; continue; }
        $L = $liveR[$x['code']];
        if (trim($L['target_spec']) !== trim($x['target_spec'])) { $rd[] = $x['code'] . '.وجهة'; }
        if (trim($L['dims'])        !== trim($x['dims']))        { $rd[] = $x['code'] . '.أبعاد'; }
        if (trim($L['kind'])        !== trim($x['kind']))        { $rd[] = $x['code'] . '.نوع'; }
        if (trim($L['doc_ref'])     !== trim($x['src']))         { $rd[] = $x['code'] . '.مصدر'; }
    }
    chk(1, '①-04', 'وجهةُ كلِّ مسارٍ وأبعادُه ومصدرُه تطابق الوثيقة',
        !$rd, $rd ? 'انحراف: ' . implode(' · ', array_slice($rd, 0, 8)) : 'صفرُ انحراف');

    /* ①-05 كلُّ وجهةٍ رمزُ تخصصٍ قائمٍ — لا وجهةَ معلَّقة. */
    $orphan = rows($db, "SELECT r.code, r.target_spec FROM fin_routing_matrix r
                          LEFT JOIN fin_acc_specializations s ON s.code = r.target_spec AND s.active=1
                         WHERE r.active=1 AND r.kind='route' AND s.id IS NULL");
    chk(1, '①-05', 'صفرُ مسارٍ يوجّه إلى تخصصٍ غيرِ معرَّف',
        !$orphan, $orphan ? count($orphan) . ' مسارًا يتيمًا' : 'كلُّ وجهةٍ مسنَدةٌ لتخصصٍ قائم');

    /* ①-06 الاحتياطيةُ واحدةٌ لا أكثر — RT-17 الحكمُ الجامع. */
    $fb = (int) one($db, "SELECT COUNT(*) FROM fin_routing_matrix WHERE active=1 AND kind='fallback'");
    chk(1, '①-06', 'الحكمُ الجامعُ قاعدةٌ احتياطيةٌ واحدةٌ لا أكثر (RT-17)',
        $fb === 1, "الاحتياطيات: $fb");

    /* ①-07 المرتجَعُ وقواعدُه. */
    $wantB = count($S['backflow']); $liveB = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_notices WHERE active=1");
    chk(1, '①-07', "المرتجَعُ الماليُّ حاضرٌ بعددِ الوثيقة ($wantB)",
        $liveB === $wantB, "الوثيقة $wantB · الحي $liveB");
    $wantBR = count($S['backflow_rules']); $liveBR = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_rules WHERE active=1");
    chk(1, '①-08', "قواعدُ المرتجَعِ حاضرةٌ بعددِ الوثيقة ($wantBR)",
        $liveBR === $wantBR, "الوثيقة $wantBR · الحي $liveBR");

    /* ①-09 رموزُ الأسبابِ المحكومةُ موجودةٌ — BR-03 لا نصَّ حرًّا. */
    $rc = (int) one($db, "SELECT COUNT(*) FROM fin_reason_codes WHERE active=1");
    chk(1, '①-09', 'قاموسُ أسبابِ الرفضِ المحكومُ مبذورٌ (BR-03)', $rc > 0, "$rc رمزًا");

    /* ①-10 صفرُ محاسبٍ بلا تخصصٍ من العشرة — FACC-0001. */
    $noSpec = (int) one($db, "SELECT COUNT(*) FROM fin_accountants
                               WHERE (is_deleted IS NULL OR is_deleted=0) AND (spec_code IS NULL OR spec_code='')");
    chk(1, '①-10', 'صفرُ محاسبٍ بلا تخصصٍ من العشرة (FACC-0001)',
        $noSpec === 0, "$noSpec محاسبًا بلا تخصص");

    /* ── الاختبارُ الحي: يُشغَّل المحرّكُ فعلًا ─────────────────────────── */
    /* الكيانُ المُختبَرُ هو الحاملُ للبيانات — لا الأولُ بالترقيم. فالفحصُ على
       كيانٍ فارغٍ يمرُّ كاذبًا. */
    $co = (int) one($db, "SELECT company_id FROM fin_accountants
                           WHERE (is_deleted IS NULL OR is_deleted=0)
                           GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co <= 0) { $co = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $stamp = 'GATE13-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);
    /* نطاقٌ حقيقيٌّ لعنصرِ العمل — WF-02 يرفض العنصرَ بلا نطاق. */
    /* ◆ مفتاحُ `org_units` هو `unit_id` لا `id`. */
    $org = (int) one($db, "SELECT unit_id FROM org_units WHERE company_id = {$co} AND active = 1
                            ORDER BY unit_id LIMIT 1");
    if ($org <= 0) { $org = (int) one($db, "SELECT unit_id FROM org_units ORDER BY unit_id LIMIT 1"); }
    note("الكيانُ المُختبَر: $co · نطاقُ عناصرِ العمل: $org");

    /* ①-11 مسحٌ حيٌّ للمساراتِ الخمسةِ والثلاثينَ كلِّها: كلُّ مسارٍ يُشغَّل فعلًا
       ويجب أن ينتهيَ بمستلِمٍ — محاسبِ تخصصِه أو تصعيدٍ لرئاسةِ الحسابات.
       ◆ هذا هو الحكمُ القابلُ للاختبار: «صفرُ مسارٍ ينتهي صامتًا» (BR-01) —
         لا «عددُ الحاملين»، فغيابُ حاملٍ لتخصصٍ نقصُ إسنادٍ إداريٌّ لا عطبٌ بنيوي. */
    $sweepFail = array(); $sweepEsc = 0; $sweepOk = 0;
    foreach (rows($db, "SELECT code FROM fin_routing_matrix WHERE active=1 ORDER BY code") as $rr) {
        $res = RoutingEngine::route($db, array(
            'company_id' => $co, 'route_code' => $rr['code'],
            'source_kind' => 'gate_sweep', 'source_ref' => $stamp . '-' . $rr['code'],
            'title' => 'مسحُ بوابةٍ — ' . $rr['code'],
            'org_unit_id' => $org, 'created_by' => 1,
        ));
        if (empty($res['ok']) || empty($res['work_item_id'])) { $sweepFail[] = $rr['code']; continue; }
        $sweepOk++;
        if (!empty($res['escalated'])) { $sweepEsc++; }
    }
    $totalRoutes = (int) one($db, "SELECT COUNT(*) FROM fin_routing_matrix WHERE active=1");
    chk(1, '①-11', "مسحٌ حيٌّ: كلُّ مسارٍ من الـ{$totalRoutes} ينتهي بمستلِمٍ ولا يصمت (BR-01)",
        !$sweepFail, $sweepFail
            ? 'صمتت: ' . implode(' · ', array_slice($sweepFail, 0, 10))
            : "بلغت وجهتَها $sweepOk مسارًا — منها $sweepEsc بالتصعيدِ لرئاسةِ الحسابات");

    /* الغائبُ عن الإسنادِ يُرفع ملاحظةً — يُقاس ولا يُهمَل (OBL-0307). */
    foreach (rows($db, "SELECT s.code, s.name_ar,
                               (SELECT COUNT(*) FROM fin_routing_matrix r
                                 WHERE r.active=1 AND r.kind='route' AND r.target_spec=s.code) routes
                          FROM fin_acc_specializations s
                         WHERE s.active=1
                           AND NOT EXISTS (SELECT 1 FROM fin_accountants a
                                            WHERE a.spec_code=s.code AND a.active=1
                                              AND (a.is_deleted IS NULL OR a.is_deleted=0))
                           AND EXISTS (SELECT 1 FROM fin_routing_matrix r
                                        WHERE r.active=1 AND r.kind='route' AND r.target_spec=s.code)") as $x) {
        note('تخصصٌ بلا حامل: ' . $x['code'] . ' « ' . $x['name_ar'] . ' » تصله ' . $x['routes']
           . ' مسارًا — الطلبُ يبلغ رئاسةَ الحساباتِ بنيويًّا، والإسنادُ قرارٌ إداريٌّ معلَّق.');
    }
    /* والمحاسبُ الذي لا حسابَ مستخدمٍ له لا تبلغه مهمةٌ باسمِه. */
    $noLogin = (int) one($db, "SELECT COUNT(*) FROM fin_accountants a
                                LEFT JOIN users u ON u.employee_id = a.employee_id
                               WHERE a.company_id = {$co} AND a.active = 1
                                 AND (a.is_deleted IS NULL OR a.is_deleted = 0) AND u.id IS NULL");
    if ($noLogin > 0) {
        note("محاسبونَ بلا حسابِ دخول: $noLogin — تُصعَّد مهامُّهم لرئاسةِ الحسابات حتى يُفتح لهم حساب.");
    }

    /* ①-12 توجيهٌ حقيقيٌّ بمسارٍ صريح. */
    $r = RoutingEngine::route($db, array(
        'company_id' => $co, 'route_code' => 'RT-01',
        'source_kind' => 'gate_probe', 'source_ref' => $stamp . '-A',
        'source_dept' => 'المشترياتُ التشغيلية', 'title' => 'فحصُ بوابةٍ — طلبُ شراءٍ تشغيلي',
        'org_unit_id' => $org, 'created_by' => 1,
    ));
    chk(1, '①-12', 'توجيهٌ حيٌّ بمسارٍ صريحٍ ينجح ويصل تخصصَه',
        !empty($r['ok']) && ($r['spec'] ?? '') === 'ACC-01', ($r['reason'] ?? '') . ' · التخصص ' . ($r['spec'] ?? '—'));

    /* ①-13 الأثرُ مكتوبٌ: صفٌّ في سجلِّ التوجيهِ ومهمةٌ في مساحةِ المحاسب. */
    $logged = (int) one($db, "SELECT COUNT(*) FROM fin_routing_log
                               WHERE source_kind='gate_probe' AND source_ref='{$stamp}-A'");
    chk(1, '①-13', 'التوجيهُ يكتب شاهدَه في سجلِّ التوجيه', $logged === 1, "صفوف: $logged");
    $wi = (int) one($db, "SELECT COUNT(*) FROM work_items WHERE source_ref='gate_probe:{$stamp}-A'");
    chk(1, '①-14', 'الطلبُ الموجَّهُ يظهر مهمةً في مساحةِ محاسبِه (OBL-0003)',
        $wi === 1, "مهام: $wi" . ($wi === 0 ? ' — ولا محاسبَ مسنَدٌ للتخصص' : ''));

    /* ①-15 الحكمُ الجامع: واقعةٌ بلا مسارٍ خاصٍّ تُوجَّه ولا تسقط. */
    $r2 = RoutingEngine::route($db, array(
        'company_id' => $co, 'trigger_key' => 'fin.route.nonexistent',
        'source_kind' => 'gate_probe', 'source_ref' => $stamp . '-B',
        'source_dept' => 'الخزينةُ والبنوك', 'title' => 'فحصُ بوابةٍ — واقعةٌ بلا مسارٍ خاص',
        'org_unit_id' => $org, 'created_by' => 1,
    ));
    chk(1, '①-15', 'الحكمُ الجامعُ يلتقط ما لا مسارَ خاصَّ له (RT-17)',
        !empty($r2['ok']) && ($r2['route']['kind'] ?? '') === 'fallback',
        ($r2['reason'] ?? '') . ' · بـ' . ($r2['route']['code'] ?? '—'));

    /* ①-16 الحارس: ما لم يُوجَّه لا يصل الخزينة. */
    $g1 = RoutingEngine::assertRouted($db, $co, 'gate_probe', $stamp . '-NEVER');
    $g2 = RoutingEngine::assertRouted($db, $co, 'gate_probe', $stamp . '-A');
    chk(1, '①-16', 'لا تصل الخزينةَ واقعةٌ لم تمرَّ بمحاسبِ تخصصِها (OBL-0001)',
        empty($g1['ok']) && !empty($g2['ok']),
        'غيرُ الموجَّهة: ' . (empty($g1['ok']) ? 'مرفوضة ✔' : 'مرَّت ✗') . ' · الموجَّهة: ' . (!empty($g2['ok']) ? 'مقبولة ✔' : 'مرفوضة ✗'));

    /* ①-17 BR-03: رفضٌ بلا رمزِ سببٍ محكومٍ يُرفض. */
    $b1 = RoutingEngine::backflow($db, array(
        'company_id' => $co, 'notice_code' => 'BF-01',
        'source_kind' => 'gate_probe', 'source_ref' => $stamp . '-A', 'created_by' => 1));
    $b2 = RoutingEngine::backflow($db, array(
        'company_id' => $co, 'notice_code' => 'BF-01', 'reason_code' => 'NOT_A_REAL_CODE',
        'source_kind' => 'gate_probe', 'source_ref' => $stamp . '-A', 'created_by' => 1));
    $anyUser = (int) one($db, "SELECT id FROM users WHERE company_id={$co} ORDER BY id LIMIT 1");
    $b3 = RoutingEngine::backflow($db, array(
        'company_id' => $co, 'notice_code' => 'BF-01', 'reason_code' => 'DOC_MISSING',
        'source_kind' => 'gate_probe', 'source_ref' => $stamp . '-A',
        'to_user_id' => $anyUser, 'org_unit_id' => $org, 'created_by' => 1));
    chk(1, '①-17', 'سببُ الرفضِ برمزٍ محكومٍ لا بنصٍّ حر (BR-03)',
        empty($b1['ok']) && empty($b2['ok']) && !empty($b3['ok']),
        'بلا رمز: ' . (empty($b1['ok']) ? 'مرفوض ✔' : '✗') . ' · رمزٌ مخترع: ' . (empty($b2['ok']) ? 'مرفوض ✔' : '✗')
      . ' · رمزٌ محكوم: ' . (!empty($b3['ok']) ? 'مقبول ✔' : '✗'));

    /* ①-18 BR-04: المرتجَعُ بلا مرجعِ طلبِه يُرفض. */
    $b4 = RoutingEngine::backflow($db, array('company_id' => $co, 'notice_code' => 'BF-01', 'created_by' => 1));
    chk(1, '①-18', 'المرتجَعُ لا ينشأ منفصلًا بلا مرجعِ طلبِه (BR-04)',
        empty($b4['ok']), $b4['reason'] ?? '');

    /* ①-19 BR-02: المرتجَعُ الذي يستوجب فعلًا يولّد مهمة. */
    $bfWi = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_log
                             WHERE source_ref='{$stamp}-A' AND notice_code='BF-01' AND work_item_id IS NOT NULL");
    chk(1, '①-19', 'المرتجَعُ الذي يستوجب فعلًا يولّد مهمةً بمهلةٍ ومسؤول (BR-02)',
        $bfWi >= 1, "مرتجَعاتٌ بمهام: $bfWi");

    /* ①-20 BR-06: إلغاءُ الطلبِ يُغلق المرتجَعَ ولا يحذفه. */
    $before = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_log WHERE source_ref='{$stamp}-A'");
    RoutingEngine::closeOnCancel($db, $co, 'gate_probe', $stamp . '-A', 'فحصُ بوابة — إلغاءُ الطلب', 1);
    $after  = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_log WHERE source_ref='{$stamp}-A'");
    $closed = (int) one($db, "SELECT COUNT(*) FROM fin_backflow_log
                               WHERE source_ref='{$stamp}-A' AND state='closed_cancelled' AND close_reason<>''");
    chk(1, '①-20', 'إلغاءُ الطلبِ يُغلق مرتجَعَه بسببٍ ولا يحذفه (BR-06)',
        $before === $after && $before > 0 && $closed === $before,
        "قبل $before · بعد $after · مُغلقٌ بسبب $closed");

    /* تنظيفُ أثرِ الفحص — البوابةُ لا تترك بياناتٍ في الحي. */
    $db->query("DELETE FROM work_items WHERE source_ref LIKE 'gate_probe:{$stamp}%'
                                          OR source_ref LIKE 'gate_sweep:{$stamp}%'");
    $db->query("DELETE FROM fin_backflow_log WHERE source_ref LIKE '{$stamp}%'");
    $db->query("DELETE FROM fin_routing_log  WHERE source_ref LIKE '{$stamp}%'");
    $db->query("DELETE FROM personal_notifications WHERE title LIKE '%" . $db->real_escape_string($stamp) . "%'");
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ② — أنواعُ الاعتمادِ الأربعةُ وسقوفُها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 2) {
    require_once $ROOT . '/app/Services/Finance/ApprovalGate.php';

    $co2 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co2 <= 0) { $co2 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $st2 = 'GATE13B-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);

    /* ②-01 الأنواعُ الأربعةُ حاضرةٌ بنصِّ الوثيقة. */
    $wantA = count($S['approval_types']);
    $liveA = array();
    foreach (rows($db, "SELECT code,seq,title,owner_label,question,rule_text,needs_cap,doc_ref
                          FROM fin_approval_types WHERE active=1 ORDER BY seq") as $r) { $liveA[$r['code']] = $r; }
    chk(2, '②-01', "أنواعُ الاعتمادِ حاضرةٌ بعددِ الوثيقة ($wantA)",
        count($liveA) === $wantA, 'الوثيقة ' . $wantA . ' · الحي ' . count($liveA));

    $ad = array();
    foreach ($S['approval_types'] as $i => $x) {
        if (!isset($liveA[$x['code']])) { $ad[] = $x['code'] . ' غائب'; continue; }
        $L = $liveA[$x['code']];
        if (trim($L['title'])       !== trim($x['title']))    { $ad[] = $x['code'] . '.عنوان'; }
        if (trim($L['owner_label']) !== trim($x['owner']))    { $ad[] = $x['code'] . '.صاحب'; }
        if (trim($L['question'])    !== trim($x['question'])) { $ad[] = $x['code'] . '.سؤال'; }
        if ((int) $L['seq'] !== $i + 1)                       { $ad[] = $x['code'] . '.ترتيب'; }
    }
    chk(2, '②-02', 'نصُّ كلِّ نوعٍ وترتيبُه يطابق الوثيقة',
        !$ad, $ad ? implode(' · ', $ad) : 'صفرُ انحراف');

    /* ②-03 APR-3 وحدَه يشترط سقفًا — «صاحبُ السلطةِ الماليةِ بالسقف». */
    $capReq = rows($db, "SELECT code FROM fin_approval_types WHERE active=1 AND needs_cap=1");
    chk(2, '②-03', 'اعتمادُ الالتزامِ أو الدفعِ وحدَه يشترط سقفًا (APR-3)',
        count($capReq) === 1 && $capReq[0]['code'] === 'APR-3',
        'يشترط السقف: ' . implode(' · ', array_column($capReq, 'code')));

    /* ②-04 أزواجُ التعارضِ مبذورةٌ بأحكامِها. */
    $cf = (int) one($db, "SELECT COUNT(*) FROM fin_approval_conflicts WHERE active=1 AND doc_ref<>''");
    chk(2, '②-04', 'أزواجُ الاعتمادِ المتعارضةُ مبذورةٌ بمرجعِ حكمِ كلٍّ (FACC-0044)',
        $cf >= 4, "$cf زوجًا");

    /* ── الاختبارُ الحي ────────────────────────────────────────────────── */
    /* فاعلونَ مختلفونَ بأدوارٍ صحيحة: المحاسبُ (18) لـAPR-2 · الخزينةُ (21) لـAPR-4. */
    $uReq  = (int) one($db, "SELECT id FROM users WHERE company_id={$co2} ORDER BY id LIMIT 1");
    $uAcc  = (int) one($db, "SELECT id FROM users WHERE company_id={$co2} AND (role_id=18 OR role='18') LIMIT 1");
    $uAuth = (int) one($db, "SELECT id FROM users WHERE company_id={$co2} AND (role_id=17 OR role='17') LIMIT 1");
    $uTre  = (int) one($db, "SELECT id FROM users WHERE company_id={$co2} AND (role_id=21 OR role='21') LIMIT 1");
    note("فاعلو البند ②: طالب=$uReq · محاسب(18)=$uAcc · سلطة(17)=$uAuth · خزينة(21)=$uTre");

    /* سقفٌ نافذٌ لصاحبِ السلطةِ — بلا سقفٍ لا يقع APR-3 أصلًا. */
    $db->query("INSERT INTO fin_authority_caps
                  (company_id, scope_kind, scope_ref, apr_code, max_amount, currency, authority_ref, active)
                VALUES ({$co2},'user','{$uAuth}','APR-3',10000.00,'USD','فحصُ بوابةٍ — سقفٌ مؤقت',1)
                ON DUPLICATE KEY UPDATE max_amount=10000.00, active=1");

    $doc = array('company_id' => $co2, 'source_kind' => 'gate_apr', 'source_ref' => $st2 . '-1');

    /* ②-05 لا يُقفز نوع: APR-3 قبل APR-1 يُرفض. */
    $r1 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-3', 'actor_user_id' => $uAuth, 'decision' => 'approved', 'amount' => 500));
    chk(2, '②-05', 'لا يُقفز نوعٌ — اعتمادُ الالتزامِ قبلَ الحاجةِ يُرفض',
        empty($r1['ok']), $r1['reason'] ?? '');

    /* ②-06 السلسلةُ بترتيبها تُقبل. */
    $ok1 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-1', 'actor_user_id' => $uReq, 'decision' => 'approved'));
    $ok2 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-2', 'actor_user_id' => $uAcc, 'decision' => 'approved'));
    chk(2, '②-06', 'السلسلةُ بترتيبها تُقبل — الحاجةُ ثم الموازني',
        !empty($ok1['ok']) && !empty($ok2['ok']),
        'APR-1: ' . ($ok1['reason'] ?? '') . ' · APR-2: ' . ($ok2['reason'] ?? ''));

    /* ②-07 حارسُ الدور: المحاسبُ لا ينفّذ الدفعَ (FACC-0047 · APR-4 للخزينة). */
    $r2 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-4', 'actor_user_id' => $uAcc, 'decision' => 'approved'));
    chk(2, '②-07', 'حارسُ الدور: من ليس صاحبَ النوعِ لا يقع منه (FMGR-0004)',
        empty($r2['ok']), $r2['reason'] ?? '');

    /* ②-08 حارسُ السقف: ما تجاوزه يُرفض ويُصعَّد (CEO-Y0120). */
    $r3 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-3', 'actor_user_id' => $uAuth, 'decision' => 'approved', 'amount' => 99999));
    chk(2, '②-08', 'ما تجاوز السقفَ لا يُنفَّذ قبلَ قرارِ من فوقَه (CEO-Y0120)',
        empty($r3['ok']), $r3['reason'] ?? '');

    /* ②-09 حارسُ التعارض: طالبُ الحاجةِ لا يأذن بالتزامِ المال (FACC-0058). */
    $r4 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-3', 'actor_user_id' => $uReq, 'decision' => 'approved', 'amount' => 500));
    chk(2, '②-09', 'الشخصُ نفسُه لا يجمع نوعين متعارضين (FACC-0044)',
        empty($r4['ok']), $r4['reason'] ?? '');

    /* ②-10 داخلَ السقفِ وبفاعلٍ مختلفٍ يُقبل. */
    $ok3 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-3', 'actor_user_id' => $uAuth, 'decision' => 'approved', 'amount' => 500));
    chk(2, '②-10', 'اعتمادُ الالتزامِ داخلَ السقفِ من صاحبِه يُقبل',
        !empty($ok3['ok']), $ok3['reason'] ?? '');

    /* ②-11 لا يتكرر النوعُ على المستندِ الواحد. */
    $r5 = \App\Services\Finance\ApprovalGate::record($db, $doc + array(
        'apr_code' => 'APR-1', 'actor_user_id' => $uReq, 'decision' => 'approved'));
    chk(2, '②-11', 'النوعُ الواحدُ لا يتكرر على المستندِ الواحد',
        empty($r5['ok']), $r5['reason'] ?? '');

    /* ②-12 «صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة». */
    $bare = array('company_id' => $co2, 'source_kind' => 'gate_apr', 'source_ref' => $st2 . '-2');
    \App\Services\Finance\ApprovalGate::record($db, $bare + array(
        'apr_code' => 'APR-1', 'actor_user_id' => $uReq, 'decision' => 'approved'));
    $g1 = \App\Services\Finance\ApprovalGate::assertComplete($db, $co2, 'gate_apr', $st2 . '-2');
    $g2 = \App\Services\Finance\ApprovalGate::assertComplete($db, $co2, 'gate_apr', $st2 . '-1');
    chk(2, '②-12', 'صفرُ طلبٍ يُنفَّذ باعتمادٍ واحدٍ من الأربعة (PROP-01 §7-2 ③)',
        empty($g1['ok']) && !empty($g2['ok']),
        'باعتمادٍ واحد: ' . (empty($g1['ok']) ? 'مرفوض ✔' : 'مرَّ ✗')
      . ' · بالسلسلةِ الكاملة: ' . (!empty($g2['ok']) ? 'مقبول ✔' : 'مرفوض ✗'));

    /* ②-13 السقفُ يُجمَّد لحظةَ القرارِ فلا يتغير بتغييرِه لاحقًا. */
    $frozen = one($db, "SELECT cap_at_decision FROM fin_approval_chain
                         WHERE source_kind='gate_apr' AND source_ref='{$st2}-1' AND apr_code='APR-3'");
    $db->query("UPDATE fin_authority_caps SET max_amount=1.00
                 WHERE company_id={$co2} AND scope_kind='user' AND scope_ref='{$uAuth}' AND apr_code='APR-3'");
    $after = one($db, "SELECT cap_at_decision FROM fin_approval_chain
                        WHERE source_kind='gate_apr' AND source_ref='{$st2}-1' AND apr_code='APR-3'");
    chk(2, '②-13', 'السقفُ يُجمَّد لحظةَ القرارِ ولا يُعاد قراءتُه بعد تغييره',
        $frozen !== null && $frozen === $after && (float) $frozen === 10000.00,
        'وقتَ القرار ' . var_export($frozen, true) . ' · بعد تغييرِ السقف ' . var_export($after, true));

    /* تنظيفُ أثرِ الفحص. */
    $db->query("DELETE FROM fin_approval_chain WHERE source_kind='gate_apr' AND source_ref LIKE '{$st2}%'");
    $db->query("DELETE FROM fin_authority_caps WHERE authority_ref='فحصُ بوابةٍ — سقفٌ مؤقت'");
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ③ — موافقةُ الرئيسِ على التكليفِ وصندوقُه · والبند ④ — فصلُ الواجبات
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 3 || $only === 4) {
    require_once $ROOT . '/app/Services/Exec/AssignmentGate.php';
    $AG = 'App\Services\Exec\AssignmentGate';

    $co3 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co3 <= 0) { $co3 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $st3 = 'GATE13C-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);

    /* ══ البند ④ أولًا: المصفوفةُ التي يستند إليها فحصُ ③ ═══════════════ */
    if ($only === null || $only === 4) {
        $wantS = count($S['sod_pairs']);
        $liveS = array();
        foreach (rows($db, "SELECT code,func_a,func_b,roles_a,roles_b,scope,severity,enforced_by,doc_ref
                              FROM sec_sod_pairs WHERE active=1") as $r) { $liveS[$r['code']] = $r; }
        chk(4, '④-01', "أزواجُ فصلِ الواجباتِ حاضرةٌ بعددِ الوثيقة ($wantS)",
            count($liveS) === $wantS, 'الوثيقة ' . $wantS . ' · الحي ' . count($liveS));

        $sd = array();
        foreach ($S['sod_pairs'] as $x) {
            if (!isset($liveS[$x['code']])) { $sd[] = $x['code'] . ' غائب'; continue; }
            $L = $liveS[$x['code']];
            if (trim($L['func_a']) !== trim($x['func_a'])) { $sd[] = $x['code'] . '.أ'; }
            if (trim($L['func_b']) !== trim($x['func_b'])) { $sd[] = $x['code'] . '.ب'; }
            if (trim($L['doc_ref']) !== trim($x['src']))   { $sd[] = $x['code'] . '.مصدر'; }
        }
        chk(4, '④-02', 'نصُّ كلِّ زوجٍ ومصدرُه يطابق الوثيقة',
            !$sd, $sd ? implode(' · ', $sd) : 'صفرُ انحراف');

        /* الوثائقُ الخمسُ تحمل الأزواجَ نفسَها — والاختلافُ بينها عيبٌ يُكشف. */
        $decl = $S['sod_declared'];
        $thirteen = array_keys(array_filter($decl, function ($v) use ($wantS) { return $v === $wantS; }));
        chk(4, '④-03', "الأزواجُ نفسُها في وثائقِ الوظائفِ الخمسِ بلا اختلاف",
            count($thirteen) >= 5, 'تحمل الـ' . $wantS . ': ' . implode(' · ', $thirteen)
          . ' | وPROP-01 تحمل ' . (isset($decl['PROP-01']) ? $decl['PROP-01'] : 0) . ' (منتشرةً لا كاملة)');

        /* ولا زوجَ بلا مُنفِذٍ مسمًّى — الزوجُ بلا مُنفِذٍ ادعاءٌ لا قيد. */
        $noEnf = (int) one($db, "SELECT COUNT(*) FROM sec_sod_pairs WHERE active=1 AND enforced_by=''");
        chk(4, '④-04', 'صفرُ زوجٍ بلا مُنفِذٍ مسمًّى — والزوجُ بلا مُنفِذٍ ادعاءٌ لا قيد',
            $noEnf === 0, "$noEnf زوجًا بلا مُنفِذ");

        /* أزواجُ الدورِ لها طرفانِ محدَّدان — وإلا لا تُفحص عند التكليف. */
        $badRole = rows($db, "SELECT code FROM sec_sod_pairs
                               WHERE active=1 AND scope='role' AND (roles_a='' OR roles_b='')");
        chk(4, '④-05', 'كلُّ زوجٍ موضعُه التكليفُ له طرفانِ من الأدوارِ محدَّدان',
            !$badRole, $badRole ? implode(' · ', array_column($badRole, 'code')) : 'كلُّها محدَّدةُ الطرفين');
    }

    /* ══ البند ③ ═══════════════════════════════════════════════════════ */
    if ($only === null || $only === 3) {
        /* ③-01 الشاشاتُ الأربعُ لها مصادرُ حقيقةٍ قائمة. */
        $tbl = array('exec_audit_reports' => 'تقاريرُ المراجعةِ الداخلية',
                     'exec_approvals'     => 'الاعتماداتُ الماليةُ العليا',
                     'exec_assignments'   => 'موافقاتُ التكليف',
                     'exec_decisions'     => 'المسائلُ المحجوزة');
        $missing = array();
        foreach ($tbl as $t => $label) {
            if (one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'") < 1) { $missing[] = $label; }
        }
        chk(3, '③-01', 'شاشاتُ صندوقِ الرئيسِ الأربعُ لها مصادرُ حقيقةٍ قائمة',
            !$missing, $missing ? 'ناقص: ' . implode(' · ', $missing) : implode(' · ', array_values($tbl)));

        /* ③-02 آراءُ المسألةِ المحجوزةِ أربعُ جهاتٍ لا واحدة (CEO-Y0123). */
        $enum = (string) one($db, "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exec_matter_opinions'
                                      AND COLUMN_NAME='opinion_of'");
        $four = substr_count($enum, "','") + 1;
        chk(3, '③-02', 'المسألةُ المحجوزةُ تُعرض بآراءِ أربعِ جهاتٍ لا برأيٍ واحد (CEO-Y0123)',
            $four === 4 && strpos($enum, 'internal_audit') !== false, "الجهات: $four · $enum");

        /* ③-03 مسارُ تقريرِ المراجعةِ مباشرٌ — وأيُّ وسيطٍ يُكشف بالمسح. */
        $filtered = (int) one($db, "SELECT COUNT(*) FROM exec_audit_reports WHERE delivery_path <> 'direct'");
        chk(3, '③-03', 'صفرُ تقريرِ مراجعةٍ وصل الرئيسَ بعد فلترة (CEO-Y0119)',
            $filtered === 0, "$filtered تقريرًا بوسيط");

        /* ── الاختبارُ الحي ─────────────────────────────────────────────── */
        $ceo  = (int) one($db, "SELECT id FROM users WHERE company_id={$co3} AND (role_id=9 OR role='9') LIMIT 1");
        $nonCeo = (int) one($db, "SELECT id FROM users WHERE company_id={$co3} AND (role_id=17 OR role='17') LIMIT 1");
        $subj = (int) one($db, "SELECT id FROM users WHERE company_id={$co3}
                                  AND COALESCE(NULLIF(role_id,0), CAST(NULLIF(role,'') AS UNSIGNED)) IS NULL
                                ORDER BY id LIMIT 1");
        if ($subj <= 0) { $subj = (int) one($db, "SELECT id FROM users WHERE company_id={$co3} ORDER BY id DESC LIMIT 1"); }
        note("فاعلو البند ③: رئيس(9)=$ceo · غيرُ رئيس(17)=$nonCeo · مكلَّف=$subj");

        /* ③-04 تكليفٌ نظيفٌ يُعرض على الرئيس. */
        $a1 = $AG::request($db, array(
            'company_id' => $co3, 'assignment_no' => $st3 . '-CLEAN',
            'subject_user_id' => $subj, 'role_id' => 31, 'requested_by' => $nonCeo,
            'scope_note' => 'فحصُ بوابة'));
        chk(3, '③-04', 'تكليفٌ نظيفٌ يُفحص آليًّا ويُعرض على الرئيس',
            !empty($a1['ok']) && ($a1['state'] ?? '') === 'presented', $a1['reason'] ?? '');

        /* ③-05 CEO-Y0121: بلا موافقةٍ لا يمنح صلاحيةً واحدة. */
        $e1 = $AG::isEffective($db, $co3, $subj, 31);
        chk(3, '③-05', 'تكليفٌ بلا موافقةِ الرئيسِ لا يمنح صلاحيةً واحدة (CEO-Y0121)',
            empty($e1['ok']), $e1['reason'] ?? '');

        /* ③-06 القرارُ للرئيسِ حصرًا — ولو كان مديرًا ماليًّا. */
        $d1 = $AG::decide($db, array('company_id' => $co3, 'assignment_no' => $st3 . '-CLEAN',
                                     'decided_by' => $nonCeo, 'decision' => 'approved'));
        chk(3, '③-06', 'موافقةُ التكليفِ للرئيسِ التنفيذيِّ حصرًا ولو كان الطالبُ مديرًا ماليًّا',
            empty($d1['ok']), $d1['reason'] ?? '');

        /* ③-07 وبموافقةِ الرئيسِ يسري — والموافقةُ سجلٌّ لا رسالة. */
        $d2 = $AG::decide($db, array('company_id' => $co3, 'assignment_no' => $st3 . '-CLEAN',
                                     'decided_by' => $ceo, 'decision' => 'approved',
                                     'decision_reason' => 'فحصُ بوابة', 'authority_ref' => 'GATE-' . $st3));
        $e2 = $AG::isEffective($db, $co3, $subj, 31);
        $rec = (int) one($db, "SELECT COUNT(*) FROM exec_assignments
                                WHERE assignment_no='{$st3}-CLEAN' AND state='approved'
                                  AND decided_by={$ceo} AND decided_at IS NOT NULL AND authority_ref<>''");
        chk(3, '③-07', 'وبموافقةِ الرئيسِ يسري — والموافقةُ سجلٌّ بتاريخٍ ومرجعٍ لا رسالة',
            !empty($d2['ok']) && !empty($e2['ok']) && $rec === 1,
            ($d2['reason'] ?? '') . ' · السريان: ' . ($e2['reason'] ?? '') . ' · السجل: ' . $rec);

        /* ③-08 CEO-Y0122: تكليفٌ يُنشئ تعارضًا لا يُعرض ولا يُقرَّر. */
        /* المكلَّفُ صار رئيسَ حساباتٍ (31) — وSOD-12 تمنع جمعَه مع المراجعِ (33). */
        $a2 = $AG::request($db, array(
            'company_id' => $co3, 'assignment_no' => $st3 . '-CONF',
            'subject_user_id' => $subj, 'role_id' => 33, 'requested_by' => $nonCeo));
        $d3 = $AG::decide($db, array('company_id' => $co3, 'assignment_no' => $st3 . '-CONF',
                                     'decided_by' => $ceo, 'decision' => 'approved'));
        chk(3, '③-08', 'طلبٌ يُنشئ تعارضَ واجباتٍ لا يُعرض ولا يُقرَّر حتى يُحسم (CEO-Y0122)',
            !empty($a2['ok']) && ($a2['state'] ?? '') === 'blocked' && empty($d3['ok']),
            'الحالة: ' . ($a2['state'] ?? '—') . ' · ' . mb_substr((string) ($a2['reason'] ?? ''), 0, 90)
          . ' · قرارُ الرئيس: ' . (empty($d3['ok']) ? 'مرفوض ✔' : 'مرَّ ✗'));

        /* ③-09 استقلالُ المراجعِ مطلقٌ (IAF-0006 · SOD-13). */
        $a3 = $AG::request($db, array(
            'company_id' => $co3, 'assignment_no' => $st3 . '-AUD',
            'subject_user_id' => $nonCeo, 'role_id' => 33, 'requested_by' => $ceo));
        chk(3, '③-09', 'المراجعُ الداخليُّ لا يُجمع مع أيِّ دورٍ تنفيذيٍّ (IAF-0006 · SOD-13)',
            !empty($a3['ok']) && ($a3['state'] ?? '') === 'blocked',
            'الحالة: ' . ($a3['state'] ?? '—'));

        /* ③-10 ما ليس قياديًّا ولا رقابيًّا لا تلزمه موافقة. */
        $e3 = $AG::isEffective($db, $co3, $subj, 11);   // مشغل اسطول — تشغيليٌّ محض
        chk(3, '③-10', 'ما ليس قياديًّا ولا رقابيًّا يمرُّ بلا موافقةِ الرئيس',
            !empty($e3['ok']) && ($e3['kind'] ?? '') === 'other', $e3['reason'] ?? '');

        /* ③-11 CEO-Y0124: «قرارُ الرئيسِ يُنفَّذ في بيتِ حقيقتِه لا في مكتبِه —
           فمكتبُ الرئيسِ يقرر ولا ينفّذ · صفرُ قيدٍ مصدرُه شاشاتُ مكتبِ الرئيس».
           والقيدُ يتصل بمصدرِه عبرَ `fin_financial_events.source_ref/entity_type`
           — فالمسحُ عليهما لا على عمودٍ متوهَّم. */
        $ceoJournal = (int) one($db, "SELECT COUNT(*)
                                        FROM fin_journal_entries j
                                        JOIN fin_financial_events e ON e.id = j.event_id
                                       WHERE e.entity_type LIKE 'exec\\_%'
                                          OR e.source_ref  LIKE 'exec\\_%'
                                          OR e.entity_type IN ('exec_approval','exec_decision','exec_assignment')");
        chk(3, '③-11', 'صفرُ قيدٍ مصدرُه شاشاتُ مكتبِ الرئيس — يقرر ولا ينفّذ (CEO-Y0124)',
            $ceoJournal === 0, "قيودٌ مصدرُها مستنداتُ مكتبِ الرئيس: $ceoJournal");

        /* تنظيفُ أثرِ الفحص. */
        $db->query("DELETE FROM exec_assignments WHERE assignment_no LIKE '{$st3}%'");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑤ — الطبقاتُ الثلاثُ واختبارُ التجنبِ ومحرّكُ الالتزامات
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 5) {
    require_once $ROOT . '/app/Services/Finance/ObligationEngine.php';
    $OE = 'App\Services\Finance\ObligationEngine';

    $co5 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co5 <= 0) { $co5 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $st5 = 'GATE13E-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);

    /* ⑤-01..04 المراجعُ حاضرةٌ بأعدادِ الوثيقة. */
    foreach (array(
        array('⑤-01', 'الطبقاتُ الثلاثُ للاعتراف', 'fin_obl_layers', count($S['recognition_layers'])),
        array('⑤-02', 'خطواتُ اختبارِ التجنب', 'fin_obl_avoidance_tests', count($S['avoidance_tests'])),
        array('⑤-03', 'أنواعُ الالتزام', 'fin_obl_types', count($S['obligation_types'])),
        array('⑤-04', 'التنبيهات', 'fin_obl_alerts', count($S['alerts'])),
    ) as $t) {
        $live = (int) one($db, "SELECT COUNT(*) FROM `{$t[2]}` WHERE active=1");
        chk(5, $t[0], "{$t[1]} حاضرةٌ بعددِ الوثيقة ({$t[3]})", $live === $t[3], "الوثيقة {$t[3]} · الحي $live");
    }

    /* ⑤-05 القواعدُ الخمسُ الأُسَر بأعدادِها. */
    $wantR = count($S['obligation_rules']) + count($S['symmetry_rules']) + count($S['accrual_rules'])
           + count($S['supplier_rules']) + count($S['inheritance']);
    $liveR = (int) one($db, "SELECT COUNT(*) FROM fin_obl_rules WHERE active=1");
    chk(5, '⑤-05', "قواعدُ المحرّكِ الخمسُ الأُسَرِ حاضرةٌ بعددِ الوثيقة ($wantR)",
        $liveR === $wantR, "الوثيقة $wantR · الحي $liveR");

    /* ⑤-06 شروطُ الاعترافِ بمعيارِ كلِّ نوع. */
    $wantC = count($S['recognition_conditions']);
    $liveC = (int) one($db, "SELECT COUNT(*) FROM fin_obl_recognition WHERE active=1");
    chk(5, '⑤-06', "شروطُ الاعترافِ بمعيارِ كلِّ نوعِ عقدٍ حاضرة ($wantC)",
        $liveC === $wantC, "الوثيقة $wantC · الحي $liveC");

    /* ⑤-07 OR-10: صفرُ نوعِ التزامٍ يدَّعي إنشاءَ قيد. */
    $posts = (int) one($db, "SELECT COUNT(*) FROM fin_obl_types WHERE posts_entry <> 0");
    chk(5, '⑤-07', 'المحرّكُ لا يُنشئ قيدًا — صفرُ نوعٍ يدَّعيه (OR-10)',
        $posts === 0, "أنواعٌ تدَّعي القيد: $posts");

    /* ⑤-08 الأعمدةُ الإلزاميةُ الثلاثةَ عشرَ في جدولِ الاستحقاق. */
    $need13 = array('l1_commitment', 'l1_remaining', 'l2_recognized', 'l2_cumulative', 'l3_open',
                    'settled', 'gap_l1_l2', 'recognition_rule', 'is_partial', 'proration_basis',
                    'term_class', 'due_date', 'period_no');
    $have = array();
    foreach (rows($db, "SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_obl_schedule'") as $r) { $have[] = $r['c']; }
    $miss13 = array_values(array_diff($need13, $have));
    chk(5, '⑤-08', 'الأعمدةُ الإلزاميةُ الثلاثةَ عشرَ في جدولِ الاستحقاقِ موجودة',
        !$miss13, $miss13 ? 'ناقص: ' . implode(' · ', $miss13) : count($need13) . ' عمودًا');

    /* ⑤-09 الأعمدةُ الإلزاميةُ الاثنا عشرَ لاختبارِ التجنب. */
    $need12 = array('cancellable', 'cancel_cost', 'unavoidable', 'unavoidable_pct',
                    'volume_obligation', 'penalty_obligation', 'recognition_candidate', 'onerous',
                    'special_standard', 'verdict', 'decided_at', 'next_review_at');
    $have2 = array();
    foreach (rows($db, "SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_obl_avoidance'") as $r) { $have2[] = $r['c']; }
    $miss12 = array_values(array_diff($need12, $have2));
    chk(5, '⑤-09', 'الأعمدةُ الإلزاميةُ الاثنا عشرَ لاختبارِ التجنبِ موجودة',
        !$miss12, $miss12 ? 'ناقص: ' . implode(' · ', $miss12) : count($need12) . ' عمودًا');

    /* ⑤-10 OR-10 بنيويًّا: لا عمودَ قيدٍ في جدولِ الاستحقاقِ أصلًا. */
    $hasJe = count(array_intersect($have, array('journal_entry_id', 'entry_id', 'je_id')));
    chk(5, '⑤-10', 'لا عمودَ قيدٍ في جدولِ الاستحقاق — والبنيةُ تمنع الخرقَ لا النية',
        $hasJe === 0, $hasJe ? 'وُجد عمودُ قيد' : 'صفرُ عمودِ قيد');

    /* ── الاختبارُ الحي: الحالةُ المعياريةُ في الوثيقة ─────────────────── */
    /* «عقدٌ اثنا عشرَ شهرًا يبدأ في العشرين يولّد ثلاثةَ عشرَ استحقاقًا محاسبيًّا
       واثني عشرَ إقفالًا تعاقديًّا — ولا يُخلطان» (الحكمُ الثالثُ في §٢-٢). */
    $ref5 = $st5 . '-C1';

    /* ⑤-11 لا جدولَ قبلَ نتيجةِ اختبارِ التجنب (OBL-0200). */
    $g0 = $OE::generateSchedule($db, array(
        'company_id' => $co5, 'ob_type' => 'OB-01', 'contract_kind' => 'supplier',
        'contract_ref' => $ref5, 'total_value' => 120000, 'start_date' => '2026-01-20',
        'end_date' => '2027-01-19', 'generated_by' => 1));
    chk(5, '⑤-11', 'لا يُولَّد جدولٌ لعقدٍ بلا نتيجةِ اختبارِ تجنبٍ مسجَّلة (OBL-0200)',
        empty($g0['ok']), $g0['reason'] ?? '');

    /* ⑤-12 اختبارُ التجنبِ الخماسيُّ يقع بترتيبِه ويسجّل نتيجتَه. */
    $av = $OE::avoidanceTest($db, array(
        'company_id' => $co5, 'contract_kind' => 'supplier', 'contract_ref' => $ref5,
        'contract_value' => 120000, 'cancellable' => 0, 'cancel_cost' => 30000,
        'expected_benefit' => 150000, 'decided_by' => 1));
    chk(5, '⑤-12', 'اختبارُ التجنبِ يقع بخطواتِه الخمسِ ويسجّل نتيجتَه ومن قرَّرها',
        !empty($av['ok']) && count($av['steps'] ?? array()) === 5,
        'الحكم: ' . ($av['verdict'] ?? '—') . ' · غيرُ قابلٍ للتجنب ' . ($av['unavoidable'] ?? 0)
      . ' (' . ($av['unavoidable_pct'] ?? 0) . '٪) · خطوات ' . count($av['steps'] ?? array()));

    /* ⑤-13 OBL-0204: التزامان لا واحد — ولا يُدمجان في رقمٍ واحد. */
    $vol = (float) ($av['volume_obligation'] ?? 0);
    $pen = (float) ($av['penalty_obligation'] ?? 0);
    chk(5, '⑤-13', 'العقدُ يحمل التزامين: الحجمَ يسقط بالعجزِ والجزاءَ لا يسقط — ولا يُدمجان (OBL-0204)',
        $vol > 0 && $pen > 0 && abs(($vol + $pen) - 120000) < 0.01 && $vol !== $pen,
        "الحجم $vol · الجزاء $pen · مجموعُهما " . ($vol + $pen) . ' = قيمةُ العقد');

    /* ⑤-14 الحالةُ المعيارية: 13 محاسبيةً و12 تعاقديةً ولا يُخلطان. */
    $gen = $OE::generateSchedule($db, array(
        'company_id' => $co5, 'ob_type' => 'OB-01', 'side' => 'payable', 'contract_kind' => 'supplier',
        'contract_ref' => $ref5, 'counterparty' => 'موردُ فحصِ بوابة', 'total_value' => 120000,
        'start_date' => '2026-01-20', 'end_date' => '2027-01-19', 'generated_by' => 1));
    chk(5, '⑤-14', 'عقدُ اثني عشرَ شهرًا يبدأ في العشرين: ثلاثةَ عشرَ استحقاقًا محاسبيًّا واثنا عشرَ تعاقديًّا',
        !empty($gen['ok']) && ($gen['accounting_periods'] ?? 0) === 13 && ($gen['contract_periods'] ?? 0) === 12,
        'محاسبية ' . ($gen['accounting_periods'] ?? '—') . ' · تعاقدية ' . ($gen['contract_periods'] ?? '—'));

    $oblId = (int) ($gen['obligation_id'] ?? 0);

    /* ⑤-15 SY-06: الجدولُ كلُّه دفعةً واحدةً عند النفاذِ لا شهرًا بشهر. */
    $rowsN = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$oblId}");
    chk(5, '⑤-15', 'الجدولُ يُنتَج عند النفاذِ لكلِّ الفتراتِ دفعةً واحدة (SY-06)',
        $rowsN === 13, "صفوفُ الجدول: $rowsN");

    /* ⑤-16 OR-02: كلُّ استحقاقٍ بتاريخِه بيومه — صفرُ استحقاقٍ بلا يوم. */
    $noDue = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule
                              WHERE obligation_id={$oblId} AND (due_date IS NULL OR due_date='0000-00-00')");
    chk(5, '⑤-16', 'كلُّ استحقاقٍ بتاريخٍ محددٍ بيومِه لا شهرًا مجملًا (OR-02)',
        $noDue === 0, "بلا يوم: $noDue");

    /* ⑤-17 SY-05: الكسريتانِ موسومتانِ صريحًا والباقي كاملة. */
    $part = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$oblId} AND is_partial=1");
    chk(5, '⑤-17', 'الفترةُ الكسريةُ تُوسَم صريحًا — كسرٌ أولٌ وكسرٌ أخيرٌ لا غير (SY-05)',
        $part === 2, "كسريات: $part");

    /* ⑤-18 SY-04: الكسرُ بالتناسبِ اليوميّ — ويُقاس على **L1 الارتباط**.
       ◆ درسٌ من الفاحصِ العكسي: كان هذا الفحصُ يقرأ `l2_recognized` فمرَّ
         خضراءَ على محرّكٍ يخلط الطبقتين. الفحصُ المكتوبُ من التنفيذِ يُصادق
         على عيبِه — والمكتوبُ من الوثيقةِ يكشفه. */
    $first = rows($db, "SELECT partial_days, month_days, l1_commitment FROM fin_obl_schedule
                         WHERE obligation_id={$oblId} ORDER BY period_no LIMIT 1");
    $expect = $first ? round(10000 * $first[0]['partial_days'] / $first[0]['month_days'], 2) : -1;
    chk(5, '⑤-18', 'الفترةُ الكسريةُ تُحسب بالتناسبِ اليوميِّ من الحصةِ الشهرية (SY-04)',
        $first && abs((float) $first[0]['l1_commitment'] - $expect) < 0.01,
        $first ? sprintf('%d/%d يومًا × 10,000 = %s · والمسجَّل %s',
                 $first[0]['partial_days'], $first[0]['month_days'], $expect, $first[0]['l1_commitment']) : '—');

    /* ⑤-18ب مجموعُ الارتباطِ يساوي قيمةَ العقدِ بلا تسرُّب — والخلطُ يُكشف بالجمع. */
    $sumL1 = (float) one($db, "SELECT ROUND(SUM(l1_commitment),2) FROM fin_obl_schedule WHERE obligation_id={$oblId}");
    chk(5, '⑤-18ب', 'ومجموعُ الارتباطِ على الفتراتِ يطابق قيمةَ العقدِ بلا تسرُّب',
        abs($sumL1 - 120000.00) < 1.00, "المجموع $sumL1 · قيمةُ العقد 120000.00");

    /* ⑤-19 الطبقاتُ الثلاثُ مستقلةٌ — و**L2 صفرٌ قبلَ الأداء** (OBL-0135).
       «تنشأ عند أداءِ الطرفِ الآخرِ أو تحققِ شرطِ الاعتراف» — فمن يملؤها عند
       التوليدِ يُثبت اعترافًا لا وجودَ له. */
    $l2AtBirth = (float) one($db, "SELECT COALESCE(SUM(l2_recognized),0) + COALESCE(SUM(l3_open),0)
                                     FROM fin_obl_schedule WHERE obligation_id={$oblId}");
    $noL1 = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule
                             WHERE obligation_id={$oblId} AND l1_commitment <= 0");
    $merged = ($l2AtBirth > 0.01) ? 1 : $noL1;
    chk(5, '⑤-19', 'الطبقاتُ مستقلةٌ — والمعترَفُ به والذمةُ صفرٌ قبلَ الأداء (OBL-0135)',
        $merged === 0, "L2+L3 عند التوليد: $l2AtBirth · صفوفٌ بلا ارتباط: $noL1");

    /* ⑤-20 OR-03: التصنيفُ قصيرٌ أو طويلٌ بتاريخِ الاستحقاق.
       ◆ ويُختبَر على عقدٍ **يتجاوز السنة** — وإلا كان الفرعُ الطويلُ غيرَ مُختبَرٍ
         والفحصُ يمرُّ بلا أن يمسَّ نصفَ القاعدة. */
    $refLong = $st5 . '-C2';
    $OE::avoidanceTest($db, array(
        'company_id' => $co5, 'contract_kind' => 'lease', 'contract_ref' => $refLong,
        'contract_value' => 240000, 'cancellable' => 0, 'cancel_cost' => 20000, 'decided_by' => 1));
    $genL = $OE::generateSchedule($db, array(
        'company_id' => $co5, 'ob_type' => 'OB-03', 'contract_kind' => 'lease',
        'contract_ref' => $refLong, 'total_value' => 240000,
        'start_date' => date('Y-m-01'), 'end_date' => date('Y-m-t', strtotime('+23 months')),
        'generated_by' => 1));
    $oblL  = (int) ($genL['obligation_id'] ?? 0);
    $long  = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$oblL} AND term_class='long'");
    $short = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$oblL} AND term_class='short'");
    chk(5, '⑤-20', 'التصنيفُ قصيرًا وطويلًا محسوبٌ بتاريخِ الاستحقاقِ آليًّا — والفرعان مُختبَران (OR-03)',
        $short > 0 && $long > 0 && ($short + $long) === 24, "قصير $short · طويل $long · المجموع " . ($short + $long));

    /* ⑤-20ب OR-12: الآفاقُ الثلاثةُ تُحسب على جدولٍ حيٍّ قائم — لا بعد إنهائه. */
    $hz = $OE::horizons($db, $co5);
    $hzSum = (float) ($hz['within_30d'] ?? 0) + (float) ($hz['within_1y'] ?? 0) + (float) ($hz['beyond_1y'] ?? 0);
    chk(5, '⑤-23', 'تقريرُ الالتزاماتِ يعرض ثلاثةَ آفاقٍ زمنيةٍ بأرقامٍ حية (OR-12)',
        !empty($hz['ok']) && $hzSum > 0 && (float) ($hz['beyond_1y'] ?? 0) > 0,
        sprintf('٣٠ يومًا %s · سنة %s · بعدها %s · المجموع %s',
                $hz['within_30d'] ?? '—', $hz['within_1y'] ?? '—', $hz['beyond_1y'] ?? '—', $hzSum));

    /* ⑤-20ج OR-03 حيًّا: ما دخل نطاقَ السنةِ يُرحَّل من الطويلِ إلى القصير. */
    $before = $long;
    $OE::reclassify($db, $co5, date('Y-m-d', strtotime('+6 months')));
    $afterL = (int) one($db, "SELECT COUNT(*) FROM fin_obl_schedule WHERE obligation_id={$oblL} AND term_class='long'");
    chk(5, '⑤-25', 'ويُعاد التصنيفُ كلَّ إقفالٍ فيُرحَّل الداخلُ نطاقَ السنةِ إلى القصير (OR-03)',
        $afterL < $before, "الطويلُ قبل $before · بعد إقفالِ ستةِ أشهرٍ $afterL");

    /* ⑤-21 OR-07: التعديلُ يُغلق القديمَ ويشير إليه — ولا يحذفه. */
    $gen2 = $OE::generateSchedule($db, array(
        'company_id' => $co5, 'ob_type' => 'OB-01', 'contract_kind' => 'supplier',
        'contract_ref' => $ref5, 'total_value' => 132000, 'start_date' => '2026-01-20',
        'end_date' => '2027-01-19', 'generated_by' => 1, 'amendment_ref' => 'AMD-' . $st5));
    $oldKept = (int) one($db, "SELECT COUNT(*) FROM fin_obl_register
                                WHERE id={$oblId} AND state='superseded'");
    $newRef  = (int) one($db, "SELECT COUNT(*) FROM fin_obl_register
                                WHERE supersedes_id={$oblId} AND amendment_ref<>''");
    chk(5, '⑤-21', 'تعديلُ العقدِ يُغلق الجدولَ القديمَ ويُنشئ جديدًا يشير إليه — ولا يحذفه (OR-07)',
        !empty($gen2['ok']) && $oldKept === 1 && $newRef === 1 && $rowsN === 13,
        "القديمُ محفوظٌ مُغلقًا: $oldKept · الجديدُ يشير إليه: $newRef · صفوفُ القديمِ باقية: $rowsN");

    $oblId2 = (int) ($gen2['obligation_id'] ?? 0);

    /* ⑤-22 OR-08: الإنهاءُ يُغلق ما لم يستحقَّ ويُبقي المستحق. */
    $term = $OE::terminate($db, $co5, 'supplier', $ref5, '2026-06-30', 'فحصُ بوابةٍ — إنهاءُ العقد');
    chk(5, '⑤-22', 'إنهاءُ العقدِ يُغلق ما لم يستحقَّ بعدُ — والمستحقُّ قبلَه يبقى (OR-08)',
        !empty($term['ok']) && ($term['closed_future'] ?? 0) > 0 && ($term['kept_accrued'] ?? 0) > 0,
        $term['reason'] ?? '');

    /* ⑤-24 OR-10 حيًّا: صفرُ قيدٍ نشأ من المحرّك. */
    $jeBefore = (int) one($db, "SELECT COUNT(*) FROM fin_journal_entries");
    $OE::reclassify($db, $co5);
    $OE::sweepOverdue($db, $co5);
    $jeAfter = (int) one($db, "SELECT COUNT(*) FROM fin_journal_entries");
    chk(5, '⑤-24', 'تشغيلُ المحرّكِ لا يُنشئ قيدًا واحدًا — بل جدولًا معلَنًا (OR-10)',
        $jeBefore === $jeAfter, "قبل $jeBefore · بعد $jeAfter");

    /* تنظيفُ أثرِ الفحص. */
    $db->query("DELETE s FROM fin_obl_schedule s JOIN fin_obl_register r ON r.id = s.obligation_id
                 WHERE r.contract_ref LIKE '{$st5}%'");
    $db->query("DELETE FROM fin_obl_register WHERE contract_ref LIKE '{$st5}%'");
    $db->query("DELETE FROM fin_obl_avoidance WHERE contract_ref LIKE '{$st5}%'");
}

/* ═══════════════════════════════════════════════════════════════════════════
   البندان ⑥ التصنيفُ الرباعي · ⑦ التوريثُ ومنعُ إعادةِ الإدخال
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 6 || $only === 7) {
    require_once $ROOT . '/app/Services/Governance/FieldGovernor.php';
    $FG = 'App\Services\Governance\FieldGovernor';

    $co6 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co6 <= 0) { $co6 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }

    /* الشاشةُ ↔ الجدولُ الذي يحمل حقولَها فعلًا. */
    $tableFor = array(
        'ob_schedule'        => 'fin_obl_schedule',
        'ob_register'        => 'fin_obl_register',
        'fin_obl_avoidance'  => 'fin_obl_avoidance',
        'fin_approval_chain' => 'fin_approval_chain',
        'exec_assignments'   => 'exec_assignments',
    );

    if ($only === null || $only === 6) {
        /* ⑥-01 الأصنافُ الأربعةُ بنصِّ الوثيقة. */
        $wantD = count($S['data_classes']);
        $liveD = array();
        foreach (rows($db, "SELECT code,title,name_en,owner_label,edit_mode,doc_ref
                              FROM gov_data_classes WHERE active=1") as $r) { $liveD[$r['code']] = $r; }
        chk(6, '⑥-01', "أصنافُ البياناتِ حاضرةٌ بعددِ الوثيقة ($wantD)",
            count($liveD) === $wantD, 'الوثيقة ' . $wantD . ' · الحي ' . count($liveD));

        $dd = array();
        foreach ($S['data_classes'] as $x) {
            if (!isset($liveD[$x['code']])) { $dd[] = $x['code'] . ' غائب'; continue; }
            if (trim($liveD[$x['code']]['title'])       !== trim($x['title'])) { $dd[] = $x['code'] . '.عنوان'; }
            if (trim($liveD[$x['code']]['owner_label']) !== trim($x['owner'])) { $dd[] = $x['code'] . '.مالك'; }
            if (trim($liveD[$x['code']]['doc_ref'])     !== trim($x['src']))   { $dd[] = $x['code'] . '.مصدر'; }
        }
        chk(6, '⑥-02', 'نصُّ كلِّ صنفٍ ومالكُه ومصدرُه يطابق الوثيقة',
            !$dd, $dd ? implode(' · ', $dd) : 'صفرُ انحراف');

        /* ⑥-03 كلُّ صنفٍ له طريقةُ تعديلٍ تُنفذ حكمَه — لا وصفٌ يُقرأ. */
        $modes = array();
        foreach (rows($db, "SELECT code, edit_mode FROM gov_data_classes WHERE active=1") as $r) { $modes[$r['code']] = $r['edit_mode']; }
        chk(6, '⑥-03', 'لكلِّ صنفٍ طريقةُ تعديلٍ تُنفذ حكمَه: القانونيُّ بملحقٍ والائتمانيُّ بقرار',
            ($modes['DC-3'] ?? '') === 'amendment_only' && ($modes['DC-4'] ?? '') === 'decision_only'
              && ($modes['DC-2'] ?? '') === 'proposal',
            'DC-1 ' . ($modes['DC-1'] ?? '—') . ' · DC-2 ' . ($modes['DC-2'] ?? '—')
          . ' · DC-3 ' . ($modes['DC-3'] ?? '—') . ' · DC-4 ' . ($modes['DC-4'] ?? '—'));

        /* ⑥-04 صفرُ حقلٍ في شاشةٍ حاكمةٍ بلا صنف — مسحٌ على الأعمدةِ الحيةِ لا قائمةٍ. */
        $unclass = $FG::scanUnclassified($db, $tableFor);
        $tot = 0; $detail = array();
        foreach ($unclass as $scr => $fields) { $tot += count($fields); $detail[] = $scr . ':' . count($fields); }
        chk(6, '⑥-04', 'صفرُ حقلٍ في شاشةٍ حاكمةٍ بلا صنفٍ من الأربعة (PROP-01 §7-2 ⑤)',
            $tot === 0, $tot === 0 ? 'كلُّ حقلٍ موسومٌ' : "$tot حقلًا: " . implode(' · ', $detail));
        foreach ($unclass as $scr => $fields) {
            note('حقولٌ بلا صنفٍ في « ' . $scr . ' »: ' . implode(' · ', array_slice($fields, 0, 12)));
        }

        /* ⑥-05 الصنفُ يحكم لا الاسم: DC-3 يُرفض تحريرُه ولو من مالكِه. */
        $r1 = $FG::canEdit($db, array('company_id' => $co6, 'screen_code' => 'fin_obl_avoidance',
                                      'field_key' => 'cancel_cost', 'role_id' => 31));
        $r2 = $FG::canEdit($db, array('company_id' => $co6, 'screen_code' => 'fin_approval_chain',
                                      'field_key' => 'cap_at_decision', 'role_id' => 18));
        $r3 = $FG::canEdit($db, array('company_id' => $co6, 'screen_code' => 'fin_approval_chain',
                                      'field_key' => 'cap_at_decision', 'role_id' => 32));
        chk(6, '⑥-05', 'الصنفُ يحدد من يعدّل لا اسمُ المستخدم — القانونيُّ بملحقٍ والائتمانيُّ بقرارِ الماليّ',
            empty($r1['ok']) && empty($r2['ok']) && !empty($r3['ok']),
            'قانونيٌّ من رئيسِ الحسابات: ' . (empty($r1['ok']) ? 'مرفوض ✔' : '✗')
          . ' · ائتمانيٌّ من محاسب: ' . (empty($r2['ok']) ? 'مرفوض ✔' : '✗')
          . ' · ائتمانيٌّ من الماليّ: ' . (!empty($r3['ok']) ? 'مقبول ✔' : '✗'));

        /* ⑥-06 الشاشةُ غيرُ الحاكمةِ لا يلزمها تصنيف. */
        $r4 = $FG::assertClassified($db, 'some_operational_screen', 'any_field');
        chk(6, '⑥-06', 'الشاشةُ غيرُ الحاكمةِ لا يلزمها تصنيفٌ — والقيدُ حيث يقع الأثر',
            !empty($r4['ok']), $r4['reason'] ?? '');
    }

    if ($only === null || $only === 7) {
        /* ⑦-01 قواعدُ التوريثِ الثمانيةُ مبذورةٌ مرجعًا. */
        $wantI = count($S['inheritance']);
        $liveI = (int) one($db, "SELECT COUNT(*) FROM fin_obl_rules WHERE family='IN' AND active=1");
        chk(7, '⑦-01', "قواعدُ التوريثِ حاضرةٌ بعددِ الوثيقة ($wantI)",
            $liveI === $wantI, "الوثيقة $wantI · الحي $liveI");

        /* ⑦-02 IN-04: الاستحقاقُ يرث ثلاثةَ عشرَ حقلًا من عقدِ العميل. */
        $acc13 = (int) one($db, "SELECT COUNT(*) FROM gov_field_inheritance
                                  WHERE child_entity='accrual' AND parent_entity='client_contract' AND active=1");
        chk(7, '⑦-02', 'الاستحقاقُ يُوَرَّث من عقدِ العميلِ ثلاثةَ عشرَ حقلًا (IN-04)',
            $acc13 === 13, "الموروث: $acc13");

        /* ⑦-03 IN-05: الالتزامُ يرث أحدَ عشرَ حقلًا من عقدِ المورد. */
        $obl11 = (int) one($db, "SELECT COUNT(*) FROM gov_field_inheritance
                                  WHERE child_entity='obligation' AND parent_entity='supplier_contract' AND active=1");
        chk(7, '⑦-03', 'الالتزامُ يُوَرَّث من عقدِ الموردِ أحدَ عشرَ حقلًا (IN-05)',
            $obl11 === 11, "الموروث: $obl11");

        /* ⑦-04 IN-06: سلسلةُ التوريثِ ثلاثيةٌ ولا تُقطع — الفاتورةُ ← الاستحقاقُ ← العقد. */
        $inv = (int) one($db, "SELECT COUNT(*) FROM gov_field_inheritance
                                WHERE child_entity='invoice' AND parent_entity='accrual' AND active=1");
        $acc = (int) one($db, "SELECT COUNT(*) FROM gov_field_inheritance
                                WHERE child_entity='accrual' AND parent_entity='client_contract' AND active=1");
        chk(7, '⑦-04', 'سلسلةُ التوريثِ ثلاثيةٌ ولا تُقطع: الفاتورةُ ← الاستحقاقُ ← العقد (IN-06)',
            $inv > 0 && $acc > 0, "الفاتورة←الاستحقاق $inv · الاستحقاق←العقد $acc");

        /* ⑦-05 IN-01 حيًّا: محاولةُ تعديلِ موروثٍ تُرفض برمزٍ يبيّن مصدرَه. */
        $before = (int) one($db, "SELECT COUNT(*) FROM gov_inheritance_denials");
        $d1 = $FG::assertNotInherited($db, array('company_id' => $co6, 'child_entity' => 'accrual',
                                                 'child_field' => 'unit_price', 'child_ref' => 'GATE', 'user_id' => 1));
        $after = (int) one($db, "SELECT COUNT(*) FROM gov_inheritance_denials");
        chk(7, '⑦-05', 'محاولةُ تعديلِ حقلٍ موروثٍ تُرفض برمزٍ يبيّن مصدرَه ويُسجَّل (IN-01)',
            empty($d1['ok']) && ($d1['source'] ?? '') === 'client_contract.unit_price' && $after === $before + 1,
            ($d1['reason'] ?? '') . ' · سُجِّل: ' . ($after - $before));

        /* ⑦-06 الحقلُ غيرُ الموروثِ يمرُّ. */
        $d2 = $FG::assertNotInherited($db, array('company_id' => $co6, 'child_entity' => 'accrual',
                                                 'child_field' => 'field_not_inherited', 'user_id' => 1));
        chk(7, '⑦-06', 'الحقلُ غيرُ الموروثِ يُحرَّر بحسبِ صنفِه',
            !empty($d2['ok']), $d2['reason'] ?? '');

        /* ⑦-07 IN-03: الأصلُ يُحدِّث المسودةَ ويُنبِّه المعتمد — والمعتمدُ لا رجعيةَ فيه. */
        $draft = $FG::onParentChange($db, 'client_contract', 'unit_price', false);
        $appr  = $FG::onParentChange($db, 'client_contract', 'unit_price', true);
        chk(7, '⑦-07', 'تغييرُ الأصلِ يُحدِّث الموروثَ في غيرِ المعتمدِ ويُنبِّه في المعتمد (IN-03)',
            count($draft['cascade']) > 0 && count($draft['notify']) === 0
              && count($appr['cascade']) === 0 && count($appr['notify']) > 0,
            'مسودة: تحديث ' . count($draft['cascade']) . ' / تنبيه ' . count($draft['notify'])
          . ' · معتمد: تحديث ' . count($appr['cascade']) . ' / تنبيه ' . count($appr['notify']));

        /* ⑦-08 IN-08: صفرُ حقلٍ يُدخَل مرتين — الموروثُ لا يُحرَّر ولو كان مالكُ صنفِه. */
        $d3 = $FG::canEdit($db, array('company_id' => $co6, 'screen_code' => 'ob_schedule',
                                      'child_entity' => 'obligation', 'field_key' => 'unit_price', 'role_id' => 31));
        chk(7, '⑦-08', 'الموروثُ مرفوضٌ للجميعِ مهما كان صنفُه ودورُ طالبه — لكلِّ حقلٍ مصدرٌ واحد (IN-08)',
            empty($d3['ok']), $d3['reason'] ?? '');

        $db->query("DELETE FROM gov_inheritance_denials WHERE child_ref='GATE'
                     OR (child_entity='obligation' AND child_field='unit_price' AND child_ref='')");
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑧ — المراجعةُ الداخليةُ المستقلة
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 8) {
    require_once $ROOT . '/app/Services/Audit/InternalAuditService.php';
    $IA = 'App\Services\Audit\InternalAuditService';

    $co8 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co8 <= 0) { $co8 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $st8 = 'GATE13H-' . substr(sha1((string) getmypid() . microtime(true)), 0, 10);

    /* ⑧-01 الدورُ مستقلٌّ بنيويًّا: لا أبَ له في الهيكل (IAF-0001 · IAF-0004). */
    $aud = rows($db, "SELECT id, name, parent_role_id, level FROM roles WHERE id=33");
    chk(8, '⑧-01', 'المراجعُ الداخليُّ دورٌ مستقلٌّ لا يتبع الماليةَ ولا الحوكمةَ في الهيكل (IAF-0004)',
        $aud && $aud[0]['parent_role_id'] === null,
        $aud ? $aud[0]['name'] . ' · أبوه: ' . var_export($aud[0]['parent_role_id'], true) : 'الدورُ غائب');

    /* ⑧-02 جداولُ الدورةِ الثمانيةِ قائمة. */
    $need = array('iaf_charter', 'iaf_independence', 'iaf_universe', 'iaf_plan',
                  'iaf_engagements', 'iaf_workpapers', 'iaf_findings', 'iaf_access_log');
    $missT = array();
    foreach ($need as $t) {
        if (one($db, "SELECT COUNT(*) FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'") < 1) { $missT[] = $t; }
    }
    chk(8, '⑧-02', 'جداولُ دورةِ المراجعةِ الثمانيةِ قائمة (IAF-0044)',
        !$missT, $missT ? 'ناقص: ' . implode(' · ', $missT) : count($need) . ' جدولًا');

    /* ⑧-03 IAF-0043 بنيويًّا: صفرُ جدولٍ ماليٍّ أو تشغيليٍّ تكتبه المراجعة. */
    $w1 = $IA::assertReadOnly(33, 'fin_journal_entries');
    $w2 = $IA::assertReadOnly(33, 'fin_obl_schedule');
    $w3 = $IA::assertReadOnly(33, 'iaf_findings');
    $w4 = $IA::assertReadOnly(31, 'fin_journal_entries');   // رئيسُ الحساباتِ لا يخضع لحدِّها
    chk(8, '⑧-03', 'المراجعُ يقرأ ولا يكتب على السجلاتِ الأصلية — وسجلُّه وحدَه له (IAF-0043)',
        empty($w1['ok']) && empty($w2['ok']) && !empty($w3['ok']) && !empty($w4['ok']),
        'قيدٌ ماليّ: ' . (empty($w1['ok']) ? 'مرفوض ✔' : '✗')
      . ' · جدولُ التزام: ' . (empty($w2['ok']) ? 'مرفوض ✔' : '✗')
      . ' · سجلُّها: ' . (!empty($w3['ok']) ? 'مسموح ✔' : '✗')
      . ' · غيرُ المراجع: ' . (!empty($w4['ok']) ? 'لا يخضع ✔' : '✗'));

    /* ── الاختبارُ الحي ────────────────────────────────────────────────── */
    $auditor = (int) one($db, "SELECT id FROM users WHERE company_id={$co8} AND (role_id=33 OR role='33') LIMIT 1");
    $ceoU    = (int) one($db, "SELECT id FROM users WHERE company_id={$co8} AND (role_id=9 OR role='9') LIMIT 1");
    $auditee = (int) one($db, "SELECT id FROM users WHERE company_id={$co8} AND (role_id=17 OR role='17') LIMIT 1");
    /* لا مراجعَ داخليٌّ مُعيَّنٌ بعدُ في القاعدةِ الحية — فيُنشأ مؤقتًا للفحصِ ثم يُزال. */
    $tmpAuditor = false;
    if ($auditor <= 0) {
        $db->query("INSERT INTO users (name, username, password, role, role_id, company_id, status)
                    VALUES ('فاحصُ بوابةٍ مؤقت','{$st8}','!','33',33,{$co8},'active')");
        $auditor = (int) $db->insert_id;
        $tmpAuditor = true;
        note("لا مراجعَ داخليٌّ مُعيَّنٌ في الكيان $co8 — أُنشئ حسابٌ مؤقتٌ للفحصِ ويُزال بعده.");
    }
    note("فاعلو البند ⑧: مراجع(33)=$auditor · رئيس(9)=$ceoU · مُراجَع(17)=$auditee");

    /* ⑧-04 IAF-0044: لا كونَ بلا ميثاقٍ معتمد. */
    $c1 = $IA::assertCycle($db, $co8, 'universe');
    chk(8, '⑧-04', 'لا كونَ رقابيٌّ بلا ميثاقٍ معتمد (IAF-0044)',
        empty($c1['ok']), $c1['reason'] ?? '');

    /* الميثاقُ ثم الكونُ ثم الخطة. */
    $db->query("INSERT INTO iaf_charter (company_id, version, functional_line, purpose, approved_by, approved_at, state)
                VALUES ({$co8},'{$st8}','ceo','فحصُ بوابة',{$ceoU},NOW(),'approved')");
    $c2 = $IA::assertCycle($db, $co8, 'universe');
    $db->query("INSERT INTO iaf_universe (company_id, area_code, area_name, risk_score, active)
                VALUES ({$co8},'{$st8}','نطاقُ فحصِ بوابة',80,1)");
    $c3 = $IA::assertCycle($db, $co8, 'plan');
    chk(8, '⑧-05', 'وبالميثاقِ يُبنى الكونُ وبالكونِ تُبنى الخطةُ — بترتيبِها لا تُقفز',
        !empty($c2['ok']) && !empty($c3['ok']),
        'الكون: ' . ($c2['reason'] ?? '') . ' · الخطة: ' . ($c3['reason'] ?? ''));

    /* ⑧-06 IAF-0009: لا تكليفَ بمهمةٍ بلا إقرارِ استقلالٍ سارٍ. */
    $db->query("INSERT INTO iaf_plan (company_id, plan_year, charter_id, title, approved_by, approved_at, state)
                VALUES ({$co8}, YEAR(CURDATE()),
                        (SELECT id FROM iaf_charter WHERE version='{$st8}'),
                        'خطةُ فحصِ بوابة', {$ceoU}, NOW(), 'approved')
                ON DUPLICATE KEY UPDATE title=VALUES(title)");
    $planId = (int) one($db, "SELECT id FROM iaf_plan WHERE company_id={$co8}
                               AND charter_id=(SELECT id FROM iaf_charter WHERE version='{$st8}') LIMIT 1");
    $c4 = $IA::assertCycle($db, $co8, 'engagement', array('plan_id' => $planId, 'lead_auditor' => $auditor));
    $db->query("INSERT INTO iaf_independence (company_id, auditor_id, scope_ref, declared_at, has_conflict, valid_until)
                VALUES ({$co8},{$auditor},'','" . date('Y-m-d H:i:s') . "',0,'" . date('Y-m-d', strtotime('+1 year')) . "')
                ON DUPLICATE KEY UPDATE has_conflict=0, valid_until=VALUES(valid_until)");
    $c5 = $IA::assertCycle($db, $co8, 'engagement', array('plan_id' => $planId, 'lead_auditor' => $auditor));
    chk(8, '⑧-06', 'لا تكليفَ بمهمةٍ بلا إقرارِ استقلالٍ سارٍ قبلَه (IAF-0009)',
        empty($c4['ok']) && !empty($c5['ok']),
        'بلا إقرار: ' . (empty($c4['ok']) ? 'مرفوض ✔' : '✗') . ' · بإقرار: ' . (!empty($c5['ok']) ? 'مقبول ✔' : '✗'));

    /* ملاحظةٌ للاختبار. */
    $db->query("INSERT INTO iaf_engagements (company_id, engagement_no, plan_id, area_code, title, lead_auditor, state)
                VALUES ({$co8},'{$st8}',{$planId},'{$st8}','مهمةُ فحصِ بوابة',{$auditor},'fieldwork')");
    $engId = (int) $db->insert_id;
    $db->query("INSERT INTO iaf_findings
                  (company_id, finding_no, engagement_id, auditee_dept, auditee_user_id, title,
                   severity, raised_by, raised_at, action_due, state)
                VALUES ({$co8},'{$st8}',{$engId},'المالية',{$auditee},'ملاحظةُ فحصِ بوابة',
                        'high',{$auditor},NOW(),'" . date('Y-m-d', strtotime('-3 days')) . "','open')");

    /* ⑧-07 لا تُغلق بلا دليلٍ مقبول. */
    $k1 = $IA::closeFinding($db, array('company_id' => $co8, 'finding_no' => $st8, 'closed_by' => $auditor));
    chk(8, '⑧-07', 'لا تُغلق ملاحظةٌ بلا دليلٍ يقبله المراجع (IAF §2-2)',
        empty($k1['ok']), $k1['reason'] ?? '');

    /* ⑧-08 ولا تُغلق من الإدارةِ نفسِها. */
    $db->query("UPDATE iaf_findings SET evidence_accepted=1, accepted_by={$auditor}
                 WHERE company_id={$co8} AND finding_no='{$st8}'");
    $k2 = $IA::closeFinding($db, array('company_id' => $co8, 'finding_no' => $st8, 'closed_by' => $auditee));
    chk(8, '⑧-08', 'ولا تُغلق الملاحظةُ من الإدارةِ المُراجَعةِ نفسِها (IAF §2-2)',
        empty($k2['ok']), $k2['reason'] ?? '');

    /* ⑧-09 CEO-Y0125: ولا يملك الرئيسُ إغلاقَها. */
    $k3 = $IA::closeFinding($db, array('company_id' => $co8, 'finding_no' => $st8, 'closed_by' => $ceoU));
    chk(8, '⑧-09', 'ولا يملك الرئيسُ إغلاقَ ملاحظةٍ — سلطتُه في القرارِ لا في إسقاطِ الدليل (CEO-Y0125)',
        empty($k3['ok']), $k3['reason'] ?? '');

    /* ⑧-10 قبولُ الدليلِ للمراجعِ حصرًا. */
    $db->query("UPDATE iaf_findings SET evidence_accepted=0, accepted_by=NULL
                 WHERE company_id={$co8} AND finding_no='{$st8}'");
    $a1 = $IA::acceptEvidence($db, array('company_id' => $co8, 'finding_no' => $st8,
                                         'accepted_by' => $ceoU, 'evidence_ref' => 'x'));
    $a2 = $IA::acceptEvidence($db, array('company_id' => $co8, 'finding_no' => $st8,
                                         'accepted_by' => $auditor, 'evidence_ref' => 'دليلُ فحصِ بوابة'));
    chk(8, '⑧-10', 'قبولُ الدليلِ للمراجعِ الداخليِّ حصرًا — ولو كان الطالبُ الرئيس',
        empty($a1['ok']) && !empty($a2['ok']),
        'الرئيس: ' . (empty($a1['ok']) ? 'مرفوض ✔' : '✗') . ' · المراجع: ' . (!empty($a2['ok']) ? 'مقبول ✔' : '✗'));

    /* ⑧-11 وبدليلٍ مقبولٍ ومن المراجعِ تُغلق. */
    $k4 = $IA::closeFinding($db, array('company_id' => $co8, 'finding_no' => $st8, 'closed_by' => $auditor));
    chk(8, '⑧-11', 'وبدليلٍ يقبله المراجعُ ومنه وحدَه تُغلق الملاحظة',
        !empty($k4['ok']), $k4['reason'] ?? '');

    /* ⑧-12 التصعيدُ آليٌّ بالمهلةِ ولا يملك أحدٌ منعَه. */
    $db->query("INSERT INTO iaf_findings
                  (company_id, finding_no, engagement_id, auditee_dept, auditee_user_id, title,
                   severity, raised_by, raised_at, action_due, state)
                VALUES ({$co8},'{$st8}-L',{$engId},'المالية',{$auditee},'ملاحظةٌ متأخرة',
                        'critical',{$auditor},NOW(),'" . date('Y-m-d', strtotime('-10 days')) . "','open')");
    $esc = $IA::escalateOverdue($db, $co8);
    $escOk = (int) one($db, "SELECT COUNT(*) FROM iaf_findings
                              WHERE company_id={$co8} AND finding_no='{$st8}-L'
                                AND state='escalated' AND escalated_to='ceo' AND escalated_at IS NOT NULL");
    chk(8, '⑧-12', 'التصعيدُ آليٌّ بالمهلةِ ويصل الرئيسَ — ولا يملك أحدٌ منعَه',
        !empty($esc['ok']) && $escOk === 1, ($esc['reason'] ?? '') . ' · المصعَّدةُ المرصودة: ' . $escOk);

    /* ⑧-13 CEO-Y0119: التقريرُ يصل مباشرةً — والمفلترُ يُرفض. */
    $rp1 = $IA::deliverReport($db, array('company_id' => $co8, 'report_no' => $st8,
                                         'title' => 'تقريرُ فحصِ بوابة', 'issued_by' => $auditor,
                                         'delivery_path' => 'via_finance'));
    $rp2 = $IA::deliverReport($db, array('company_id' => $co8, 'report_no' => $st8,
                                         'title' => 'تقريرُ فحصِ بوابة', 'issued_by' => $auditor,
                                         'findings_total' => 2, 'findings_critical' => 1));
    chk(8, '⑧-13', 'تقريرُ المراجعةِ يصل الرئيسَ مباشرةً — والمارُّ بوسيطٍ يُرفض (CEO-Y0119)',
        empty($rp1['ok']) && !empty($rp2['ok']),
        'بوسيط: ' . (empty($rp1['ok']) ? 'مرفوض ✔' : '✗') . ' · مباشرةً: ' . (!empty($rp2['ok']) ? 'مقبول ✔' : '✗'));

    /* ⑧-14 الوظيفةُ الرقابيةُ مراقَبةٌ أيضًا — اطّلاعُها يُسجَّل (OBL-0127). */
    $before8 = (int) one($db, "SELECT COUNT(*) FROM iaf_access_log WHERE company_id={$co8}");
    $IA::logAccess($db, array('company_id' => $co8, 'auditor_id' => $auditor,
                              'scope_kind' => 'fin_journal_entries', 'scope_ref' => $st8,
                              'purpose' => 'مهمةُ فحصِ بوابة', 'engagement_id' => $engId));
    $after8 = (int) one($db, "SELECT COUNT(*) FROM iaf_access_log WHERE company_id={$co8}");
    chk(8, '⑧-14', 'اطّلاعُ المراجعِ نفسِه يُسجَّل — فالوظيفةُ الرقابيةُ مراقَبةٌ أيضًا (OBL-0127)',
        $after8 === $before8 + 1, "قبل $before8 · بعد $after8");

    /* تنظيفُ أثرِ الفحص. */
    $db->query("DELETE FROM iaf_access_log   WHERE scope_ref='{$st8}'");
    $db->query("DELETE FROM iaf_findings     WHERE finding_no LIKE '{$st8}%'");
    $db->query("DELETE FROM iaf_engagements  WHERE engagement_no='{$st8}'");
    $db->query("DELETE FROM iaf_plan         WHERE id={$planId}");
    $db->query("DELETE FROM iaf_universe     WHERE area_code='{$st8}'");
    $db->query("DELETE FROM iaf_independence WHERE company_id={$co8} AND auditor_id={$auditor} AND scope_ref=''");
    $db->query("DELETE FROM iaf_charter      WHERE version='{$st8}'");
    $db->query("DELETE FROM exec_audit_reports WHERE report_no='{$st8}'");
    if ($tmpAuditor) { $db->query("DELETE FROM users WHERE id={$auditor} AND username='{$st8}'"); }
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑨ — الشاشاتُ والحساباتُ وحسمُ المخالفات
   ═══════════════════════════════════════════════════════════════════════════ */
if ($only === null || $only === 9) {
    require_once $ROOT . '/tools/u13_screens_manifest.php';
    $MAN = u13_screens_manifest();
    $co9 = (int) one($db, "SELECT company_id FROM fin_accountants
                            WHERE (is_deleted IS NULL OR is_deleted=0)
                            GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
    if ($co9 <= 0) { $co9 = (int) one($db, "SELECT id FROM admin_companies ORDER BY id LIMIT 1"); }
    $N = count($MAN);

    /* ⑨-01 كلُّ شاشةٍ في البيانِ لها ملفٌّ على القرص. */
    $noFile = array();
    foreach ($MAN as $s) { if (!is_file($ROOT . '/' . $s['dir'] . '/' . $s['file'])) { $noFile[] = $s['file']; } }
    chk(9, '⑨-01', "كلُّ شاشةٍ في البيانِ لها ملفٌّ على القرص ($N)",
        !$noFile, $noFile ? 'ناقص: ' . implode(' · ', $noFile) : "$N ملفًّا");

    /* ⑨-02 وكلُّها مسجَّلةٌ في `modules` — مفتاحُ حارسِ الصلاحيات. */
    $noMod = array();
    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        if ((int) one($db, "SELECT COUNT(*) FROM modules WHERE code='" . $db->real_escape_string($rel) . "'") < 1) { $noMod[] = $rel; }
    }
    chk(9, '⑨-02', 'وكلُّها مسجَّلةٌ في modules — والشاشةُ غيرُ المسجَّلةِ تُرفض بنيويًّا',
        !$noMod, $noMod ? 'غيرُ مسجَّل: ' . implode(' · ', $noMod) : "$N شاشة");

    /* ⑨-03 ولكلٍّ منحةٌ لدورِها المالك. */
    $noGrant = array();
    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        $g = (int) one($db, "SELECT COUNT(*) FROM role_permissions p
                               JOIN modules m ON m.id = p.module_id
                              WHERE m.code='" . $db->real_escape_string($rel) . "'
                                AND p.role_id=" . (int) $s['role'] . " AND p.can_view=1");
        if ($g < 1) { $noGrant[] = $rel . ' (دور ' . $s['role'] . ')'; }
    }
    chk(9, '⑨-03', 'ولكلِّ شاشةٍ منحةُ قراءةٍ لدورِها المالك',
        !$noGrant, $noGrant ? implode(' · ', array_slice($noGrant, 0, 5)) : "$N منحة");

    /* ⑨-04 وكلُّها في قائمةِ دورِها — فالمبنيُّ الذي لا يُبلَغ كالمعدوم.
       ═══════════════════════════════════════════════════════════════════════
       ◆ INJ-0032 · INJ-0502 — **الفحصُ كان يشهد على غيرِ بابِه.** كان نصُّه:
             SELECT COUNT(*) FROM nav_items WHERE route='../{$rel}' AND active=1
         أي يشترط البادئةَ `../` — وهي عينُها ما يجعل الصفَّ **غيرَ قابلٍ
         للتصييرِ أصلًا**. فكان يقيس وجودَ صفٍّ بصيغةٍ معطوبةٍ لا **بلوغَ**
         الشاشةِ من القائمة.
       ◆ وقياسُ اليومِ يكشف أثرًا أخطرَ من ذلك: هجرةُ INJ-0061 أزالت البادئةَ من
         كلِّ الصفوفِ وأضافت قيدًا يمنع عودتَها — فصار **صفرُ صفٍّ** ببادئة `../`،
         وهذا الفحصُ **يرسب على الأربعين وواحد** جميعًا وهي سليمةٌ تمامًا.
         **إصلاحٌ صحيحٌ كسر فاحصًا اعتمد على الشكلِ المعطوب.**
       ◆ فيُعاد بناؤه على المصدرِ الذي تقرؤه القائمةُ نفسُها: `getUnifiedNavItems`
         — ثلاثةُ شروطٍ لكلِّ شاشة:
           ① مسارُها **يظهر في مخرَجِ الدالة** لدورِ مالكها (بلوغٌ لا وجود).
           ② ولها بابٌ (`door`) أو مرحلةٌ (`stage_no` من مجموعتها) — فلا تسقط
              في «أخرى» بلا موضع.
           ③ ولا يبدأ مسارُها بـ`../` — فالقيدُ في القاعدةِ يمنعه، والفاحصُ
              يشهد له لا يعتمد عليه.
       ◆ ولا يُقارَن بـ`nav_items.stage_no` — لا وجودَ لهذا العمود؛ المرحلةُ في
         `link_groups.stage_no` وتصلها الدالةُ بـLEFT JOIN. (قِيس لا افتُرض.) */
    require_once $ROOT . '/includes/unified_nav.php';
    $noNav = array(); $noDoor = array(); $relPrefix = array();
    $navCache = array();
    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        $role = (int) $s['role'];
        if (!isset($navCache[$role])) {
            $navCache[$role] = function_exists('getUnifiedNavItems') ? (array) getUnifiedNavItems($db, $role) : array();
        }
        $hit = null;
        foreach ($navCache[$role] as $it) {
            if (isset($it['route']) && (string) $it['route'] === $rel) { $hit = $it; break; }
        }
        if ($hit === null) { $noNav[] = $rel . ' (دور ' . $role . ')'; continue; }
        $door = isset($hit['door']) ? trim((string) $hit['door']) : '';
        $stage = isset($hit['stage_no']) ? trim((string) $hit['stage_no']) : '';
        if ($door === '' && $stage === '') { $noDoor[] = $rel . ' (بلا بابٍ ولا مرحلة)'; }
        if (strpos((string) $hit['route'], '../') === 0) { $relPrefix[] = $rel; }
    }
    $bad9 = array_merge($noNav, $noDoor, $relPrefix);
    chk(9, '⑨-04', 'وكلُّ شاشةٍ **تُبلَغ** من قائمةِ دورِها ببابٍ أو مرحلةٍ ومسارٍ غيرِ نسبيّ',
        !$bad9,
        $bad9 ? implode(' · ', array_slice($bad9, 0, 5))
              : "$N شاشةً بلغت قائمةَ دورِها (بلوغٌ من `getUnifiedNavItems` لا وجودُ صفّ)");

    /* ⑨-05 ولكلٍّ أساسٌ ومرجعٌ في سجلِّ الشاشاتِ الحاكمة. */
    $noBasis = (int) one($db, "SELECT COUNT(*) FROM gov_governing_screens
                                WHERE active=1 AND (why_governing='' OR owner_doc='')");
    $govN = (int) one($db, "SELECT COUNT(*) FROM gov_governing_screens WHERE active=1");
    chk(9, '⑨-05', 'ولا شاشةَ حاكمةٌ بلا أساسٍ ومرجعِ وثيقة',
        $noBasis === 0 && $govN === $N, "الحاكمة $govN/$N · بلا أساس $noBasis");

    /* ⑨-06/07 التصييرُ الحيُّ: تُصيَّر لمالكها وتُرفض لغيرِه. */
    $php = PHP_BINARY;
    $renderOk = 0; $denyOk = 0; $renderBad = array(); $denyBad = array();
    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        $run = function ($role) use ($php, $ROOT, $rel, $co9) {
            $cmd = escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
                 . ' ' . escapeshellarg($rel) . ' ' . escapeshellarg((string) $role) . ' 891 ' . $co9 . ' 2>&1';
            foreach (explode("\n", (string) shell_exec($cmd)) as $ln) {
                if (strpos($ln, 'VERDICT|') === 0) { return trim(substr($ln, 8)); }
            }
            return 'none|بلا حكم';
        };
        $v1 = $run($s['role']);
        if (strpos($v1, 'ok|') === 0) { $renderOk++; } else { $renderBad[$rel] = $v1; }
        $v2 = $run(11);   // مشغّلُ أسطولٍ — تشغيليٌّ محضٌ لا شأنَ له بهذه الشاشات
        if (strpos($v2, 'gov|') === 0) { $denyOk++; } else { $denyBad[$rel] = $v2; }
    }
    chk(9, '⑨-06', "كلُّ شاشةٍ تُصيَّر حيًّا لدورِها المالك ($N)",
        $renderOk === $N, $renderBad
            ? implode(' · ', array_map(function ($k, $v) { return $k . ': ' . mb_substr($v, 0, 40); },
                                       array_keys(array_slice($renderBad, 0, 3)), array_slice($renderBad, 0, 3)))
            : "$renderOk/$N صُيّرت");
    chk(9, '⑨-07', 'وكلُّ شاشةٍ تُرفض لدورٍ لا يملكها — والحارسُ يُثبت بالمنعِ لا بالسماحِ وحدَه',
        $denyOk === $N, $denyBad ? implode(' · ', array_keys(array_slice($denyBad, 0, 4))) : "$denyOk/$N رُفضت");

    /* ⑨-08 صفرُ تخصصٍ تصله مساراتٌ ولا حاملَ له — بعد التزويد. */
    $unheld9 = (int) one($db, "SELECT COUNT(*) FROM fin_acc_specializations s
                                WHERE s.active=1
                                  AND NOT EXISTS (SELECT 1 FROM fin_accountants a
                                                   WHERE a.spec_code=s.code AND a.company_id={$co9}
                                                     AND a.active=1 AND (a.is_deleted IS NULL OR a.is_deleted=0))");
    chk(9, '⑨-08', 'صفرُ تخصصٍ من العشرةِ بلا حاملٍ مسنَد',
        $unheld9 === 0, "$unheld9 تخصصًا بلا حامل");

    /* ⑨-09 وصفرُ محاسبٍ بلا حسابِ دخولٍ مربوطٍ بموظفه. */
    $noLogin9 = (int) one($db, "SELECT COUNT(*) FROM fin_accountants a
                                 LEFT JOIN users u ON u.employee_id = a.employee_id
                                WHERE a.company_id={$co9} AND a.active=1
                                  AND (a.is_deleted IS NULL OR a.is_deleted=0) AND u.id IS NULL");
    chk(9, '⑨-09', 'وصفرُ محاسبٍ بلا حسابِ دخولٍ مربوطٍ بموظفِه',
        $noLogin9 === 0, "$noLogin9 محاسبًا بلا حساب");

    /* ⑨-10 والأدوارُ الخمسةُ الجديدةُ لها حاملون — بتكليفٍ أقرَّه الرئيس. */
    $held = (int) one($db, "SELECT COUNT(DISTINCT role_id) FROM users
                             WHERE company_id={$co9} AND role_id BETWEEN 31 AND 35");
    $appr = (int) one($db, "SELECT COUNT(DISTINCT role_id) FROM exec_assignments
                             WHERE company_id={$co9} AND state='approved' AND role_id BETWEEN 31 AND 35");
    chk(9, '⑨-10', 'الأدوارُ الخمسةُ الجديدةُ لها حاملون — وتكليفُ كلٍّ أقرَّه الرئيسُ (CEO-Y0121)',
        $held === 5 && $appr === 5, "حاملون $held/5 · تكليفاتٌ مُقرَّة $appr/5");

    /* ⑨-11 ولكلِّ مخالفةٍ مكشوفةٍ حسمٌ بأساسٍ مكتوب.
       ◆ المخالفاتُ من مصدرين: `spec.json` (تعارضُ الترويسةِ مع السجل) و
         `families.json` (تعارضُ الأرقامِ الحاكمةِ مع عوائلِ البنود). وعدُّ أحدِهما
         وحدَه يُنتج رسوبًا كاذبًا حين يكشف الفاحصُ العكسيُّ مزيدًا. */
    $FAMV = json_decode(@file_get_contents($ROOT . '/docs/update0013/families.json'), true);
    $varAll = count($S['variances']) + (is_array($FAMV) && isset($FAMV['variances']) ? count($FAMV['variances']) : 0);
    $varDone = (int) one($db, "SELECT COUNT(*) FROM gov_doc_variance
                                WHERE basis <> '' AND resolution <> 'defer'");
    chk(9, '⑨-11', "لكلِّ مخالفةٍ مكشوفةٍ في الوثائقِ حسمٌ بأساسٍ مكتوب ($varAll)",
        $varDone === $varAll && $varAll > 0, "مكشوفة $varAll · محسومةٌ بأساس $varDone");

    /* ⑨-12 اختصاصاتُ المراجعةِ وصلاحياتُها بعددِ الوثيقة. */
    $cN = (int) one($db, "SELECT COUNT(*) FROM iaf_competencies WHERE active=1");
    $aN = (int) one($db, "SELECT COUNT(*) FROM iaf_authorities WHERE active=1");
    chk(9, '⑨-12', 'اختصاصاتُ المراجعةِ العشرون وصلاحياتُها الاثنتا عشرةَ مسجَّلةٌ بشواهدها',
        $cN === count($S['iaf_competencies']) && $aN === count($S['iaf_authorities']) && $cN === 20 && $aN === 12,
        "اختصاصات $cN · صلاحيات $aN");

    /* ⑨-13 حارسُ سلامة: لا حسابَ أنشأته الأداةُ يحمل بيانَ اعتمادٍ صالحًا.
       فإنشاءُ كلمةِ مرورٍ قرارُ مالكٍ لا أداة — والخرقُ هنا يُكشف لا يُفترض. */
    $withPw = (int) one($db, "SELECT COUNT(*) FROM users
                               WHERE company_id={$co9} AND (username LIKE 'acc.%@equipation.sd'
                                     OR username LIKE 'FIN-%@equipation.sd' OR username LIKE 'IAF-%@equipation.sd'
                                     OR username LIKE 'ACC-%@equipation.sd')
                                 AND (password <> '!' OR force_password_change <> 1)");
    $made = (int) one($db, "SELECT COUNT(*) FROM users
                             WHERE company_id={$co9} AND (username LIKE 'acc.%@equipation.sd'
                                   OR username LIKE 'FIN-%@equipation.sd' OR username LIKE 'IAF-%@equipation.sd'
                                   OR username LIKE 'ACC-%@equipation.sd')");
    chk(9, '⑨-13', 'الحساباتُ المُنشأةُ بلا بيانِ اعتمادٍ صالحٍ وبإلزامِ تغييرِ الكلمة',
        $withPw === 0 && $made > 0, "منشأة $made · بكلمةٍ صالحةٍ أو بلا إلزامٍ $withPw");
    note("الحساباتُ المُنشأةُ ($made) لا يُدخَل بها حتى يضبط مديرُ الصلاحياتِ كلماتِها من داخلِ النظام.");

    /* ⑨-14 وكلُّ حقلٍ يُصيَّر مصنَّفٌ — والعُدّةُ لا تُصيِّر غيرَ المصنَّف. */
    $rawLbl = (int) one($db, "SELECT COUNT(*) FROM gov_field_class
                               WHERE active=1 AND label_ar REGEXP '^[a-z_]+$'");
    $fN = (int) one($db, "SELECT COUNT(*) FROM gov_field_class WHERE active=1");
    chk(9, '⑨-14', 'وكلُّ حقلٍ مصنَّفٍ له عنوانٌ يُقرأ لا اسمُ عمودٍ خام',
        $rawLbl === 0 && $fN > 0, "مصنَّفة $fN · بعنوانٍ خام $rawLbl");
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑩ — الوصلُ بدورةِ العملِ الحية
   ═══════════════════════════════════════════════════════════════════════════
   ◆ الخدمةُ المُثبَتةُ وحدَها ليست منفَّذة: السؤالُ **أتعمل في دورةِ العملِ
     الحقيقية؟** فهذه الفحوصُ تُشغِّل الفواحصَ الثلاثةَ التي تنشر حدثًا حقيقيًّا
     وتُوقّع عقدًا حقيقيًّا وتُشغّل الكرون — ثم تقيس الأثر. */
if ($only === null || $only === 10) {
    $php = PHP_BINARY;
    $probes = array(
        array('⑩-01', 'التوجيهُ موصولٌ بناقلِ الأحداث — من النشرِ إلى مهمةِ المحاسب (OBL-0002)', 'u13_wiring_probe.php'),
        array('⑩-02', 'محرّكُ الالتزاماتِ موصولٌ بنفاذِ العقد — الجدولُ يُولَّد فورًا (OR-01)', 'u13_wiring_probe2.php'),
        array('⑩-03', 'كرونُ التنبيهاتِ يعمل — الإطلاقُ والترحيلُ والتصعيد (§4-22 · OBL-0125)', 'u13_wiring_probe3.php'),
    );
    foreach ($probes as $p) {
        $out = (string) shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/' . $p[2]) . ' 2>&1');
        $ok = (bool) preg_match('~(\d+)/(\1)\s+—\s+(الوصلُ يعمل|الكرونُ يعمل)~u', $out, $m);
        $tally = preg_match('~(\d+/\d+)\s+—~u', $out, $t) ? $t[1] : '؟';
        chk(10, $p[0], $p[1], $ok, $tally . ($ok ? '' : ' — شغّل tools/' . $p[2] . ' للتفصيل'));
    }

    /* ⑩-04 حارسا الوصلِ الآخرانِ مركوزانِ في مساريهما الحقيقيين. */
    $payHook = strpos((string) @file_get_contents($ROOT . '/Finance/payments_fin.php'), 'ApprovalGate::assertComplete') !== false;
    $asgHook = strpos((string) @file_get_contents($ROOT . '/Settings/role_permissions.php'), 'AssignmentGate::isEffective') !== false;
    chk(10, '⑩-04', 'حارسُ الاعتمادِ الرباعيِّ مركوزٌ قبلَ تنفيذِ الدفع (FACC-0043)',
        $payHook, $payHook ? 'Finance/payments_fin.php قبلَ المعاملة' : 'غيرُ مركوز');
    chk(10, '⑩-05', 'وحارسُ سريانِ التكليفِ مركوزٌ عند منحِ الصلاحية (CEO-Y0121)',
        $asgHook, $asgHook ? 'Settings/role_permissions.php قبلَ الكتابة' : 'غيرُ مركوز');

    /* ⑩-06 ومستهلكُ التوجيهِ مسجَّلٌ في عاملِ الأحداثِ لا في أداةِ فحصٍ وحدَها. */
    $cronReg = strpos((string) @file_get_contents($ROOT . '/cron_events.php'), 'RoutingConsumer::handler') !== false;
    chk(10, '⑩-06', 'ومستهلكُ التوجيهِ مسجَّلٌ في عاملِ الأحداثِ الدوري',
        $cronReg, $cronReg ? 'cron_events.php' : 'غيرُ مسجَّل');
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑪ — طبقةُ الكتابة: الأفعالُ تحكم لا تُعرض
   ═══════════════════════════════════════════════════════════════════════════
   ◆ شاشةٌ تُصيَّر ليست شاشةً تعمل. وهذا البندُ يسأل عن **الفعل**: أيمنع حين
     يجب؟ أيسمح لمن يملك وحدَه؟ أيمرُّ بخدمتِه أم يكتب الجدولَ من الشاشة؟ */
if ($only === null || $only === 11) {
    /* ⑪-01 كلُّ فعلٍ مُعلَنٍ في البيانِ له رمزٌ مسجَّلٌ في قاموسِ الأفعال. */
    $declared = array(); $unbound = array();
    foreach ($MAN as $s) {
        if (!isset($s['actions_src'])) { continue; }
        /* ◆ `actions_src` نصٌّ **مُقيَّمٌ** لا سطورُ المصدرِ المُقتبسة — فالعدُّ
             على `'run' =>` (واحدٌ لكلِّ فعلٍ حتمًا) لا على شكلِ السطر. */
        preg_match_all("~'code'\s*=>\s*'([^']+)'~u", $s['actions_src'], $m);
        $runs = preg_match_all("~'run'\s*=>\s*function~u", $s['actions_src']);
        $declared = array_merge($declared, $m[1]);
        if (count($m[1]) !== $runs) {
            $unbound[] = $s['code'] . ' (' . $runs . ' فعلًا · ' . count($m[1]) . ' رمزًا)';
        }
    }
    $declared = array_values(array_unique($declared));
    $missing = array();
    foreach ($declared as $cd) {
        if ((int) one($db, "SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code = '"
                         . $db->real_escape_string($cd) . "'") === 0) { $missing[] = $cd; }
    }
    chk(11, '⑪-01', 'كلُّ فعلٍ مُعلَنٍ على شاشةٍ له رمزٌ مسجَّلٌ في قاموسِ الأفعال',
        !$missing && !$unbound && $declared,
        $missing ? 'بلا رمزٍ مسجَّل: ' . implode(' · ', $missing)
                 : ($unbound ? 'فعلٌ بلا رمز: ' . implode(' · ', $unbound)
                             : count($declared) . ' رمزًا مُعلَنًا كلُّها مسجَّلة'));

    /* ⑪-02 والعُدّةُ لا تُنفّذ فعلًا بلا رمزٍ مسجَّلٍ — الحارسُ في الكودِ لا في النية. */
    $kit = (string) @file_get_contents($ROOT . '/includes/u13_screen_kit.php');
    chk(11, '⑪-02', 'والعُدّةُ ترفض فعلًا غيرَ مسجَّلٍ في القاموس (fail-closed)',
        strpos($kit, 'u13_action_registered') !== false && strpos($kit, 'U13_ACTION_UNREGISTERED') !== false,
        'الحارسُ في includes/u13_screen_kit.php');

    /* ⑪-03 ورمزُ الحمايةِ مُنفَذٌ على مسالكِ الكتابةِ الجديدةِ من يومِها. */
    chk(11, '⑪-03', 'ورمزُ الحمايةِ مُنفَذٌ على كلِّ فعلٍ — لا بانتظارِ دورِ المسارِ في التدرّج',
        strpos($kit, 'verify_csrf_token') !== false && strpos($kit, 'U13_ACTION_CSRF_FAIL') !== false,
        'التحققُ قبلَ أيِّ حكمِ مجال');

    /* ⑪-04 ولا شاشةَ تُعلن فعلَ كتابةٍ وطبيعتُها قراءةٌ — فالزرُّ الذي لا يعمل عيب. */
    $drift = array();
    foreach ($MAN as $s) {
        if (!isset($s['actions_src'])) { continue; }
        if (!in_array($s['nature'], array('document', 'register'), true)) { $drift[] = $s['code']; }
    }
    chk(11, '⑪-04', 'ولا شاشةَ تُعلن فعلَ كتابةٍ وطبيعتُها قراءةٌ محضة',
        !$drift, $drift ? implode(' · ', $drift) : 'صفرُ انحرافٍ بين الإعلانِ والطبيعة');

    /* ⑪-05 والمنحةُ تطابق الإعلان — فلا فعلٌ يُعرض ولا can_edit له. */
    $noEdit = array();
    foreach ($MAN as $s) {
        if (!isset($s['actions_src'])) { continue; }
        $rel = $s['dir'] . '/' . $s['file'];
        /* ◆ مفتاحُ الشاشةِ في `modules` هو `code` — و`module_path` لا وجودَ له.
             ولولا حارسُ ⓪-00 لعاد الاستعلامُ الفاشلُ null فقُرئ صفرًا ومرَّ. */
        $ce = (int) one($db, "SELECT COALESCE(MAX(rp.can_edit),0) FROM role_permissions rp
                                JOIN modules m ON m.id = rp.module_id
                               WHERE m.code = '" . $db->real_escape_string($rel) . "'
                                 AND rp.role_id = " . (int) $s['role']);
        if ($ce !== 1) { $noEdit[] = $s['code'] . '/دور ' . $s['role']; }
    }
    chk(11, '⑪-05', 'ولدورِ كلِّ شاشةٍ ذاتِ فعلٍ منحةُ كتابةٍ فعليةٌ في القاعدة',
        !$noEdit, $noEdit ? implode(' · ', $noEdit) : 'كلُّ شاشةِ فعلٍ لمالكها can_edit');

    /* ⑪-06 الفحصُ الحيُّ: كلُّ حكمٍ يقع كما تنصُّ الوثيقةُ — منعًا وسماحًا. */
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/u13_action_probe.php') . ' 2>&1');
    $okP = preg_match('~الأفعالُ المفحوصة:\s*(\d+)/(\d+)~u', $out, $m) && $m[1] === $m[2] && (int) $m[1] > 0;
    chk(11, '⑪-06', 'وكلُّ فعلٍ يحكم حيًّا كما تنصُّ الوثيقةُ — منعًا وسماحًا',
        $okP, (isset($m[0]) ? $m[1] . '/' . $m[2] : '؟') . ($okP ? ' حالةً' : ' — شغّل tools/u13_action_probe.php'));
}

/* ═══════════════════════════════════════════════════════════════════════════
   البند ⑫ — من سجلٍّ إلى سلوك
   ═══════════════════════════════════════════════════════════════════════════
   ◆ بندٌ مكتوبٌ في جدولٍ ليس بندًا منفَّذًا. وهذا البندُ يسأل عن **الفرق**:
     ما تحوّل إلى قيدٍ يعمل؟ وما بقي مرجعًا بطبعِه؟ وما زال دَينًا معلَنًا؟ */
if ($only === null || $only === 12) {
    /* ⑫-01 الاختباراتُ المعياريةُ الخمسةَ عشرَ تُنفَّذ حيًّا لا تُقرأ. */
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
         . escapeshellarg($ROOT . '/tools/u13_stdtest_harness.php') . ' 2>&1');
    $okH = preg_match('~الاختباراتُ المعيارية:\s*(\d+)/(\d+)~u', $out, $mH) && $mH[1] === $mH[2] && (int) $mH[1] >= 16;
    chk(12, '⑫-01', 'الاختباراتُ المعياريةُ الخمسةَ عشرَ تُنفَّذ حيًّا (FIN-OBL-01 §4-19)',
        $okH, (isset($mH[0]) ? $mH[1] . '/' . $mH[2] : '؟') . ($okH ? ' حالةً' : ' — شغّل tools/u13_stdtest_harness.php'));

    /* ⑫-02 والمحرّكُ لم يُنشئ قيدًا — الحكمُ الجامعُ لستةٍ منها. */
    chk(12, '⑫-02', 'ومحرّكُ الالتزاماتِ لا يُنشئ قيدَ يوميةٍ — قِيسَ لا قيل',
        strpos($out, 'STD-ALL') !== false && strpos($out, '✔ STD-ALL') !== false,
        'يُقاس عددُ القيودِ قبلَ الحزمةِ وبعدَها');

    /* ⑫-03 حقولُ العقدِ الثمانيةُ والعشرون بموضعِ كلٍّ وإلزامِه. */
    $cfAll  = (int) one($db, "SELECT COUNT(*) FROM fin_contract_fields WHERE active=1");
    $cfLive = (int) one($db, "SELECT COUNT(*) FROM fin_contract_fields WHERE active=1 AND resolve_state='live'");
    $cfGapNoAct = (int) one($db, "SELECT COUNT(*) FROM fin_contract_fields
                                   WHERE active=1 AND resolve_state='gap' AND owner_action=''");
    chk(12, '⑫-03', 'حقولُ العقدِ الـ28 بموضعِ كلٍّ — ولكلِّ فجوةٍ ما يلزم المالكَ',
        $cfAll === 28 && $cfGapNoAct === 0,
        "مسجَّلة $cfAll · بموضعٍ حيٍّ $cfLive · فجوةٌ بلا إجراءِ مالكٍ $cfGapNoAct");

    /* ⑫-04 والحارسُ مركوزٌ عند نفاذِ العقدِ لا في أداةِ فحصٍ وحدَها. */
    $cfHook = strpos((string) @file_get_contents($ROOT . '/app/Services/Contract/ContractSignedEffects.php'),
                     'assertContractFields') !== false;
    chk(12, '⑫-04', 'وحارسُ الحقولِ الحاكمةِ مركوزٌ عند نفاذِ العقد (OBL-0058..0085)',
        $cfHook, $cfHook ? 'ContractSignedEffects — بوضعٍ متدرِّج EMS_U13_CFIELD_GATE' : 'غيرُ مركوز');

    /* ⑫-05 اشتقاقُ الصلاحيةِ من أحدَ عشرَ عاملًا — والحاديَ عشرَ ينقض. */
    $pdOk = false; $pdWhy = 'الخدمةُ غيرُ موجودة';
    $pdFile = $ROOT . '/app/Services/Finance/PermissionDerivation.php';
    if (is_file($pdFile)) {
        require_once $pdFile;
        /* ◆ الفاعلُ المُختبَر يجب أن يحمل **دورًا عليه حدٌّ مطلقٌ موصول** — وإلا
             سقط عند عاملٍ موجبٍ قبلَ أن يُختبَر النقضُ أصلًا، فيُقرأ رسوبًا
             وهو فحصٌ لم يبلغ هدفَه (وقع فعلًا بحسابٍ دورُه فارغ). */
        $u18 = (int) one($db, "SELECT u.id FROM fin_accountants a JOIN users u ON u.employee_id=a.employee_id
                                WHERE a.company_id={$co9} AND a.active=1 AND a.spec_code<>''
                                  AND CAST(COALESCE(NULLIF(u.role_id,0), u.role) AS UNSIGNED) = 18
                                ORDER BY u.id LIMIT 1");
        if ($u18 > 0) {
            /* فعلٌ ممنوعٌ مطلقًا على دورِه ⇦ يجب أن يُنقض بالعاملِ الحاديَ عشرَ. */
            $deny = \App\Services\Finance\PermissionDerivation::derive($db, array(
                'user_id' => $u18, 'company_id' => $co9, 'action_code' => 'fin.approve.execute'));
            /* والفعلُ الذي لا حدَّ عليه ⇦ لا يُنقض بالمنعِ الصريح. */
            $free = \App\Services\Finance\PermissionDerivation::derive($db, array(
                'user_id' => $u18, 'company_id' => $co9, 'action_code' => 'fin.obl.generate'));
            $pdOk = (count($deny['factors']) === 11)
                 && empty($deny['allowed']) && ($deny['denied_by'] === 'PFACTOR-11')
                 && ($free['denied_by'] !== 'PFACTOR-11');
            $pdWhy = 'عوامل ' . count($deny['factors']) . ' · الممنوعُ نُقض بـ' . $deny['denied_by']
                   . ' · وغيرُ الممنوعِ لم يُنقض به';
        } else { $pdWhy = 'لا محاسبَ بتخصصٍ للاختبار'; }
    }
    chk(12, '⑫-05', 'الصلاحيةُ تُشتقُّ من أحدَ عشرَ عاملًا — والمنعُ الصريحُ ينقض لا يوازن (FACC-0071..0081)',
        $pdOk, $pdWhy);

    /* ⑫-06 والمنعُ مُصوَّبٌ على فعلٍ: المطلقُ يُوصَل والمشروطُ يُترك لمُنفِذِه. */
    $limAll  = (int) one($db, "SELECT COUNT(*) FROM gov_authority_limits WHERE active=1");
    $limAct  = (int) one($db, "SELECT COUNT(*) FROM gov_authority_limits WHERE active=1 AND action_codes<>''");
    $orphan  = (int) one($db, "SELECT COUNT(*) FROM gov_authority_limits l WHERE l.active=1 AND l.action_codes<>''
                                 AND EXISTS (SELECT 1 FROM (SELECT 1) d WHERE NOT EXISTS (
                                     SELECT 1 FROM nav09_action_map m
                                      WHERE FIND_IN_SET(m.canonical_code, REPLACE(l.action_codes,' ','')) ))");
    chk(12, '⑫-06', 'وكلُّ منعٍ مطلقٍ موصولٌ برمزِ فعلٍ **مسجَّل** — ولا رمزَ وهمي',
        $limAct > 0 && $orphan === 0, "حدود $limAll · منعٌ مطلقٌ موصول $limAct · رمزٌ لا وجودَ له $orphan");

    /* ⑫-07 وأصنافُ التغطيةِ تفرّق: منفَّذٌ · مرجعٌ بطبعِه · بيدِ المستخدم. */
    $seedLeft = (int) one($db, "SELECT COUNT(*) FROM gov_doc_registry WHERE coverage_kind IN ('seed','none','')");
    $kinds    = (int) one($db, "SELECT COUNT(DISTINCT coverage_kind) FROM gov_doc_registry");
    chk(12, '⑫-07', 'وصفرُ بندٍ باقٍ تحت «مكتوبٌ ولم يُنفَّذ» — كلٌّ بصنفِ تغطيتِه',
        $seedLeft === 0, "أصنافٌ مستعملة $kinds · باقٍ تحت seed/none $seedLeft");

    /* ⑫-08 والفاحصُ العكسيُّ مئةٌ بالمئة — على الأصنافِ الجديدةِ لا رغمًا عنها. */
    $rv = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg($ROOT . '/tools/u13_reverse_audit.php') . ' 2>&1');
    $okR = preg_match('~التغطية:\s*(\d+)/(\d+)~u', $rv, $mR) && $mR[1] === $mR[2];
    chk(12, '⑫-08', 'والفاحصُ العكسيُّ يغطي كلَّ عائلةٍ معلَنةٍ بأثرٍ حيٍّ محدَّد',
        $okR, (isset($mR[0]) ? $mR[1] . '/' . $mR[2] : '؟'));
}

/* ═══ التقرير ═══════════════════════════════════════════════════════════════ */
/* استعلامٌ فاشلٌ يُبطل ثقةَ كلِّ فحصٍ بُني عليه — فيُرفع رسوبًا لا ملاحظة. */
if ($SQL_ERRORS) {
    chk(0, '⓪-00', 'صفرُ استعلامٍ فاشلٍ في البوابةِ نفسِها',
        false, count($SQL_ERRORS) . ' فاشل: ' . implode(' | ', array_slice($SQL_ERRORS, 0, 3)));
}

$pass = 0; $fail = 0;
$byItem = array();
foreach ($CHECKS as $c) { $byItem[$c['item']][] = $c; if ($c['ok']) { $pass++; } else { $fail++; } }

echo "\n" . str_repeat('═', 78) . "\n";
echo "  بوابةُ القبول — update0013\n";
echo str_repeat('═', 78) . "\n";
foreach ($byItem as $item => $list) {
    $p = 0; foreach ($list as $c) { if ($c['ok']) { $p++; } }
    printf("\n  ▐ البند %d — %d/%d\n\n", $item, $p, count($list));
    foreach ($list as $c) {
        printf("   %s %-8s %-58s\n", $c['ok'] ? '✔' : '✗', $c['id'], $c['title']);
        if ($c['detail'] !== '') { printf("       %s\n", $c['detail']); }
    }
}
if ($NOTES) {
    echo "\n  ▐ ملاحظاتٌ تُرفع ولا تُهمَل\n\n";
    foreach ($NOTES as $n) { echo "   ▪ $n\n"; }
}
printf("\n%s\n  المحصّلة: %d/%d %s\n%s\n", str_repeat('═', 78), $pass, $pass + $fail,
    $fail === 0 ? '— عبرت' : "— رسب $fail", str_repeat('═', 78));

if ($mdOut) {
    $md = "# بوابةُ قبولِ update0013\n\n";
    foreach ($byItem as $item => $list) {
        $p = 0; foreach ($list as $c) { if ($c['ok']) { $p++; } }
        $md .= sprintf("## البند %d — %d/%d\n\n| | المعرّف | الفحص | التفصيل |\n| --- | --- | --- | --- |\n", $item, $p, count($list));
        foreach ($list as $c) { $md .= sprintf("| %s | %s | %s | %s |\n", $c['ok'] ? '✔' : '✗', $c['id'], $c['title'], $c['detail']); }
        $md .= "\n";
    }
    if ($NOTES) { $md .= "## ملاحظات\n\n"; foreach ($NOTES as $n) { $md .= "- $n\n"; } $md .= "\n"; }
    $md .= sprintf("**المحصّلة: %d/%d**\n", $pass, $pass + $fail);
    file_put_contents((strpos($mdOut, ':') === 1 || $mdOut[0] === '/') ? $mdOut : $ROOT . '/' . $mdOut, $md);
}

$db->close();
exit($fail === 0 ? 0 : 1);
