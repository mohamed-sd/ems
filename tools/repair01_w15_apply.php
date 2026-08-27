<?php
/**
 * tools/repair01_w15_apply.php — أداةُ إنزالِ المرحلةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تكتب الدفاترَ وتُسجِّل الأسطحَ وتُعيد الملكيّةَ** — ولا تقرّر عن المالك.
 *   وكلُّ قرارٍ يحتاج جوابَه يُسجَّل مؤجَّلًا في `repair01_w15_deferred`
 *   ببيانِ ما بُني رغمَه. ⛔ **ولا يُخمَّن جوابُه.**
 *
 * ◆ **وأسطحُ النموِّ تُختَم `W15`** ولا يُمَسُّ أساسُ السجلِّ (‏RPR-PATCH-02).
 *
 * ◆ **وإعادةُ الملكيّةِ تسبق الإسقاطَ** (‏§٤-٢): الموازنةُ والقوائمُ والعقودُ
 *   والمخاطرُ تعود إلى إداراتِها **ثمّ** يُبنى الإسقاطُ التنفيذيّ. وكلُّ نقلةٍ
 *   بعذرٍ مكتوبٍ في `repair01_w15_nav_moves` — ⛔ **ولا حذف**.
 *
 * التشغيل: php tools/repair01_w15_apply.php [--report] [--revert]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w15_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REPORT = in_array('--report', $argv, true);
$REVERT = in_array('--revert', $argv, true);
$TODAY  = '2026-08-27';

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w15_one($conn, $sql); };
$W = function ($sql) use ($conn, $REPORT) {
    if ($REPORT) { return true; }
    if ($conn->query($sql) === true) { return true; }
    echo '  ✘ ' . $conn->error . ' ⇐ ' . mb_substr(preg_replace('~\s+~', ' ', $sql), 0, 140) . "\n";
    return false;
};

echo "══ REPAIR01 · W15 — المساحاتُ والتقارير ══" . ($REPORT ? ' (تقرير فقط)' : '') . "\n\n";

/* ═══════════════════════════════════════════════════════════════════════════
   ⓪ التراجع — يفرّغ ما كتبته الأداةُ وحدَها
   ═══════════════════════════════════════════════════════════════════════════ */
if ($REVERT) {
    echo "⓪ تراجعٌ — تفريغُ دفاترِ المرحلةِ وأسطحِ نموِّها ─────────────────\n";
    foreach (repair01_w15_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $conn->query("DELETE FROM nav_items WHERE route = '$rt'");
        $conn->query("DELETE FROM nav_canonical WHERE route = '$rt'");
        $conn->query("DELETE FROM role_permissions WHERE module_id IN (SELECT id FROM modules WHERE code = '$rt')");
        $conn->query("DELETE FROM modules WHERE code = '$rt'");
        $conn->query("DELETE FROM repair01_screen_registry WHERE route = '$rt' AND origin = 'W15'");
        $conn->query("DELETE FROM gov_screen_cycle WHERE screen_file = '" . $esc(basename($s['route']))
                   . "' AND inputs_note LIKE 'RPR-W15 %'");
    }
    /* إعادةُ الملكيّةِ تُرَدُّ من دفترِ النقلاتِ نفسِه — لا من ذاكرة. */
    $r = $conn->query("SELECT route, before_val FROM repair01_w15_nav_moves WHERE move_kind = 'OWNER_RETURN'");
    while ($r && ($x = $r->fetch_assoc())) {
        $conn->query("UPDATE repair01_screen_registry SET owner_code = '" . $esc($x['before_val'])
                   . "' WHERE route = '" . $esc($x['route']) . "'");
    }
    foreach (array('repair01_w15_launcher', 'repair01_w15_scope_axis', 'repair01_w15_space_writes',
                   'repair01_w15_thresholds', 'repair01_w15_nav_moves', 'repair01_w15_fixes',
                   'repair01_w15_journey', 'repair01_w15_sod', 'repair01_w15_states',
                   'repair01_w15_deferred', 'repair01_w15_decisions', 'repair01_w15_sidebar',
                   'repair01_w15_scope') as $t) {
        $conn->query("DELETE FROM $t");
    }
    $conn->query("DELETE FROM gov_request_type WHERE src_ref LIKE 'RPR-W15%'");
    $conn->query("UPDATE repair01_events SET contract_status='NONE', contract_stage='' WHERE wave = 'W15'");
    $conn->query("DELETE FROM repair01_events WHERE wave = 'W15'");
    echo "  ✔ فُرِّغت الدفاترُ ونُزعت أسطحُ النموِّ من السجلّ\n";
    exit(0);
}

/* ═══════════════════════════════════════════════════════════════════════════
   ① سجلُّ أنواعِ الطلباتِ — القاعدةُ الرباعيّة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① سجلُّ أنواعِ الطلبات — القاعدةُ الرباعيّة ────────────────────\n";
/* **السجلُّ معزولٌ بالكيان** (`DEC-OPEN-03`): نوعُ الطلبِ يُسجَّل **لكلِّ كيانٍ
   قانونيٍّ حيٍّ** — وكيانٌ واحدٌ يُخدَم ويُترك الباقي يعني مساحةَ عملٍ عمياءَ
   في بقيّةِ الكيانات. ⛔ **ولا يُمرَّر `company_id` مخترَعٌ** — من سجلِّ
   المستخدمين الحيِّ وحدَه. */
$companies = array();
$rc = $conn->query("SELECT DISTINCT company_id FROM users WHERE company_id > 0 ORDER BY company_id");
while ($rc && ($x = $rc->fetch_row())) { $companies[] = (int) $x[0]; }
if (!$companies) { $companies = array(0); }
$typeN = 0; $typeSkip = array();
foreach (repair01_w15_launcher_types() as $t) {
  /* ⛔ **ولا يُسجَّل نوعٌ بلا خدمةِ مالكٍ قائمةٍ على القرص** — التسجيلُ بلا
       خدمةٍ يعِد بتوجيهٍ لا يُنفَّذ، والسجلُّ حينَها يكذب. */
  if (!repair01_w15_service_exists($ROOT, $t['service'])) { $typeSkip[] = $t['type_code']; continue; }
  foreach ($companies as $companyId) {
    $W("INSERT INTO gov_request_type
        (company_id, type_code, version_no, name_ar, definition_owner_dept, registry_governed_by,
         authority_rule_id, routing_rule_ref, permission_policy, exception_policy, state,
         owner_table, owner_service, projection_user_col, src_ref)
        VALUES ($companyId,'" . $esc($t['type_code']) . "',1,'" . $esc($t['name_ar']) . "',
                '" . $esc($t['owner']) . "','DEP-08','" . $esc($t['authority']) . "',
                '" . $esc($t['routing']) . "','" . $esc($t['perm']) . "','EXCEPTION_VIA_GOVERNANCE','active',
                '" . $esc($t['table']) . "','" . $esc($t['service']) . "','" . $esc($t['user_col']) . "',
                'RPR-W15 §١ · القاعدة الرباعية')
        ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), definition_owner_dept=VALUES(definition_owner_dept),
          authority_rule_id=VALUES(authority_rule_id), routing_rule_ref=VALUES(routing_rule_ref),
          owner_table=VALUES(owner_table), owner_service=VALUES(owner_service),
          projection_user_col=VALUES(projection_user_col), state='active'");
    $W("INSERT INTO repair01_w15_launcher
        (type_code, name_ar, definition_owner, registry_gov, authority_rule, routing_rule,
         owner_table, owner_service, projection_col, local_store, verdict, src_ref)
        VALUES ('" . $esc($t['type_code']) . "','" . $esc($t['name_ar']) . "','" . $esc($t['owner']) . "',
                'DEP-08','" . $esc($t['authority']) . "','" . $esc($t['routing']) . "',
                '" . $esc($t['table']) . "','" . $esc($t['service']) . "','" . $esc($t['user_col']) . "',
                '','LAUNCHER_ONLY','RPR-W15 §١')
        ON DUPLICATE KEY UPDATE owner_table=VALUES(owner_table), owner_service=VALUES(owner_service),
          projection_col=VALUES(projection_col), verdict=VALUES(verdict)");
    $typeN++;
  }
}
printf("  أنواعٌ مسجَّلةٌ بمالكِها وخدمتِها %d · متخطًّى بلا خدمةٍ قائمةٍ %d%s\n\n",
    $typeN, count($typeSkip), $typeSkip ? ' ⇐ ' . implode('، ', $typeSkip) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ② إعادةُ الملكيّة — **تسبق الإسقاطَ** (‏§٤-٢)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "② إعادةُ الملكيّةِ إلى إداراتِها ──────────────────────────────\n";
/* ⛔ **محصورةٌ فيما سمّاه النصّ**: الموازنةُ والقوائمُ والعقودُ والمخاطرُ —
     ومعها أسطحُ المساحةِ الشخصيّةِ التي كانت مقيَّدةً على مكتبِ الرئيس.
     وكلُّ صفٍّ بعذرٍ مكتوب، ⛔ **ولا حذف ولا إعادةَ ترقيم**. */
$RETURN = array(
    array('Finance/budget_master.php',      'DEP-05', 'الموازنة معاملة مالية تملكها الإدارة المالية لا مكتب الرئيس'),
    array('Reports/margin_report.php',      'DEP-05', 'الهامش رقم مالي مشتق من الدفتر لا من مكتب الرئيس'),
    array('Reports/approval_lag_report.php','DEP-05', 'تأخر الاعتماد المالي يقاس عند الإدارة المالية'),
    /* ⛔ **وسطحانِ خرجا من قائمةِ الإعادةِ بحكمٍ مُغلَقٍ سابق**:
         `Workforce/contract_registry.php` و`Clients/commercial_risks.php` حكمت فيهما
         `W10` بـ`W10_CROSS_UNIT_KEPT` ونصُّها: «سطحٌ تسمّيه وحدتان — ليس ملكَ الوحدةِ
         المشقوقةِ وحدَها، **وتغييرُ مالكِه يتجاوز ولايةَ الشقّ**».
         فنقلُهما هنا **نقضُ قرارٍ مُغلَقٍ نظر في المسألةِ وامتنع** —
         ⛔ **ولا يُنقَض حكمٌ مُغلَقٌ بحاجةِ مرحلةٍ تالية**. رُفعا للمالك في `W15-P-06`. */
    array('Risk/risk_dept_ceo.php',         'DEP-09', 'لوحة المخاطر تملكها إدارة المخاطر والقيادة تقرؤها'),
    array('Portal/business_models.php',     'DEP-01', 'نماذج العمل ووحدات القياس تعريف تعاقدي'),
    array('Tickets/ticket_kpi.php',         'DEP-10', 'مؤشرات البلاغات تملكها إدارة البلاغات'),
    array('Reports/daily_units_report.php', 'DEP-11', 'سجل الوحدات اليومية واقعة تشغيل'),
    array('Portal/my_achievement.php',      'WS-MY',  'إنجازي سطح مساحة شخصية لا سطح قيادة'),
    array('Portal/my_portal.php',           'WS-MY',  'بوابتي سطح مساحة شخصية لا سطح قيادة'),
    array('Portal/my_tasks.php',            'WS-MY',  'مهامي سطح مساحة شخصية لا سطح قيادة'),
    array('FinRequests/my_requests.php',    'WS-MY',  'طلباتي سطح مساحة شخصية لا سطح قيادة'),
);
$GHOST_RETURN = array(
    array('fin_statements.php', 'DEP-05', 'القوائم المالية تملكها الإدارة المالية'),
    array('margin.php',         'DEP-05', 'الهامش رقم مالي'),
);
$movedN = 0;
foreach ($RETURN as $m) {
    list($rt, $to, $why) = $m;
    $from = (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE route = '" . $esc($rt) . "' LIMIT 1");
    /* ⚠ **والسجلُّ الثاني يُوافَق في كلِّ تشغيلٍ لا في أوّلِه**: أداةٌ تتخطّى
         الصفَّ لأنَّ السجلَّ الأوّلَ نُقل سلفًا **تترك الثانيَ متفرّقًا للأبد**
         — وهو عطبُ «الاشتقاقُ يقرأ ما خزّنه» بعينِه. */
    $mirrorFrom = ($from === '' || $from === $to) ? 'EX-CEO' : $from;
    $rtBack0 = str_replace('/', '\\', $rt);
    $W("UPDATE repair01_surfaces SET canonical_code = '" . $esc($to) . "',
            canon_rule = 'W15_OWNER_RETURN', canon_why = '" . $esc($why) . "'
         WHERE canonical_code = '" . $esc($mirrorFrom) . "'
           AND (disk_path = '" . $esc($rt) . "' OR disk_path = '" . $esc($rtBack0) . "')
           AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM repair01_surfaces) t
                            WHERE (t.disk_path = '" . $esc($rt) . "' OR t.disk_path = '" . $esc($rtBack0) . "')
                              AND t.canonical_code = '" . $esc($to) . "')");
    if ($from === '' || $from === $to) { continue; }
    $W("UPDATE repair01_screen_registry SET owner_code = '" . $esc($to) . "',
            ownership_verdict = 'DOMAIN_SOURCE',
            verdict_rule = 'RPR-W15 §٤-٢ · إعادة ملكية من مكتب الرئيس إلى إدارتها', verdict_at = NOW()
         WHERE route = '" . $esc($rt) . "'");
    /* ⚠ **والسجلّانِ لا يتفرّقان** (‏حكمُ `W10-05`): ملكيّةٌ تُنقَل في سجلٍّ
         وتبقى في الآخرِ **تصنع نزاعًا لا إصلاحًا**.
       ⛔ **ولا يُدهَس صفٌّ حيّ**: مصفوفةُ الدراسةِ تُدرِج السطحَ الواحدَ تحت
         أكثرَ من إدارةٍ عمدًا، فالنقلةُ تمسُّ **صفَّ المِلكيّةِ القديمةِ وحدَه**
         بمسارِه — بفاصلٍ أيًّا كان اتّجاهُه — ولا تمسُّ إخوتَه.
       (‏وقعت النقلةُ في السجلِّ الثاني أعلاه قبلَ حارسِ التخطّي.) */
    $W("INSERT INTO repair01_w15_nav_moves (route, move_kind, before_val, after_val, why)
        VALUES ('" . $esc($rt) . "','OWNER_RETURN','" . $esc($from) . "','" . $esc($to) . "','" . $esc($why) . "')");
    $movedN++;
}
foreach ($GHOST_RETURN as $m) {
    list($f, $to, $why) = $m;
    $from = (string) $one("SELECT owner_code FROM repair01_screen_registry
                            WHERE screen_file = '" . $esc($f) . "' AND (route IS NULL OR route = '') LIMIT 1");
    if ($from === '' || $from === $to) { continue; }
    $W("UPDATE repair01_screen_registry SET owner_code = '" . $esc($to) . "',
            verdict_rule = 'RPR-W15 §٤-٢ · إعادة ملكية مستهدف من مكتب الرئيس', verdict_at = NOW()
         WHERE screen_file = '" . $esc($f) . "' AND (route IS NULL OR route = '')");
    $W("INSERT INTO repair01_w15_nav_moves (route, move_kind, before_val, after_val, why)
        VALUES ('" . $esc($f) . "','OWNER_RETURN_TARGET','" . $esc($from) . "','" . $esc($to) . "','" . $esc($why) . "')");
    $movedN++;
}
$leadTxn = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                        WHERE owner_code IN ('EX-CEO','EX-DVP')
                          AND ownership_verdict = 'DOMAIN_SOURCE'");
printf("  ملكيّاتٌ أُعيدت %d · معاملةٌ مملوكةٌ للقيادةِ بعدها %d\n\n", $movedN, $leadTxn);

/* ═══════════════════════════════════════════════════════════════════════════
   ③ أسطحُ النموِّ — مختومةٌ بـW15
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③ أسطحُ النموِّ — مختومةٌ بـW15 ─────────────────────────────────\n";
require_once $ROOT . '/app/Services/Ui/UiLabelRegistry.php';
$newN = 0; $navN = 0; $permN = 0; $labelN = 0; $missing = array(); $collide = array();
$maxSid = (int) preg_replace('/\D/', '', (string) $one("SELECT screen_id FROM repair01_screen_registry
                                                          ORDER BY screen_id DESC LIMIT 1"));
foreach (repair01_w15_new_surfaces() as $s) {
    $rt = $esc($s['route']); $file = basename($s['route']);
    if (!is_file($ROOT . '/' . $s['route'])) { $missing[] = $s['route']; continue; }

    /* ⛔ **ولا يُبنى ملفٌّ باسمِ شبحٍ في لقطةِ الدراسة** — سوابقُ W9-D-08 و
         W11-D-11 و W12-D-04 و W13 §٢-د و W14 ⑦: بناؤه باسمِه يفكُّ شبحيّتَه
         فيتفرَّق مخزونُ الدفاترِ عن الحيِّ ويسقط حاجبُ أساسٍ مُغلَق. */
    $ghostSid = (string) $one("SELECT screen_id FROM repair01_screen_registry
                                WHERE screen_file = '" . $esc($file) . "'
                                  AND (route IS NULL OR route = '') LIMIT 1");
    if ($ghostSid !== '') { echo '  ⛔ ' . $s['route'] . " يصطدم باسمِ شبحٍ — لا يُسجَّل\n";
                            $collide[] = $s['route']; continue; }

    /* ⓐ الموديول */
    $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    if ($modId === 0) {
        $ownerRole = (int) $one("SELECT owner_role_id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        $W("INSERT INTO modules (name, code, owner_role_id, is_link, icon, display_order, owner_dept_note)
            VALUES ('" . $esc($s['ar']) . "','$rt'," . ($ownerRole > 0 ? $ownerRole : 'NULL') . ",'0',
                    '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'" . $esc($s['owner']) . "')");
        $modId = (int) $one("SELECT id FROM modules WHERE code = '$rt' LIMIT 1");
    }

    /* ⓑ المنحُ — من الشقيقِ **المقيسِ ببلوغِه** لا بقربِه الموضوعيّ */
    if ($modId > 0) {
        $sibMod = (int) $one("SELECT id FROM modules WHERE code = '" . $esc($s['sibling']) . "' LIMIT 1");
        if ($sibMod > 0) {
            $sibGrants = (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = $sibMod AND can_view = 1");
            if ($sibGrants === 0) { echo '  ⚠ شقيقُ ' . $s['route'] . " بصفرِ منح — البلوغُ يبقى صفرًا\n"; }
            $W("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                SELECT rp.role_id, $modId, 1, 0, 0, 0
                  FROM role_permissions rp WHERE rp.module_id = $sibMod AND rp.can_view = 1
                ON DUPLICATE KEY UPDATE can_view = 1");
            $permN += (int) $one("SELECT COUNT(*) FROM role_permissions WHERE module_id = $modId");
        } else { echo '  ⚠ لا شقيقَ في الموديولات لـ' . $s['route'] . " — لا منحَ يُشتقّ\n"; }
    }

    /* ⓒ المسمّى يُسجَّل قبل أن يُصيَّر */
    if (!$REPORT) {
        $lr = \App\Services\Ui\UiLabelRegistry::register($conn, 'screen:' . strtolower($s['route']), $s['ar'], array(
            'allowed_context' => 'SIDEBAR SCREEN_TITLE',
            'source_table' => 'nav_canonical', 'source_column' => 'canonical_ar',
            'source_key' => $s['route'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W15_NEW_SURFACE_LABEL', 'origin' => 'W15',
            'src_ref' => 'RPR-W15 §٤ · سطحُ نموٍّ مختوم', 'caller' => 'repair01_w15_apply.php',
        ));
        if (!$lr['ok']) { echo '  ⚠ رُدَّ مسمّى ' . $s['route'] . ' — ' . $lr['code'] . ': ' . $lr['detail'] . "\n"; }
        else { $labelN++; }
        $gr = \App\Services\Ui\UiLabelRegistry::register($conn, 'group:w15:' . $s['group'],
            repair01_w15_group_ar($s['group']), array(
            'allowed_context' => 'SIDEBAR', 'source_table' => 'nav_canonical', 'source_column' => 'group_name',
            'source_key' => $s['group'], 'owner_code' => $s['owner'],
            'visibility_class' => 'USER_VISIBLE', 'label_state' => 'ACTIVE',
            'rule_id' => 'W15_CYCLE_GROUP_LABEL', 'origin' => 'W15',
            'src_ref' => 'RPR-W15 §٤ · مجموعةُ دورةِ العمل', 'caller' => 'repair01_w15_apply.php',
        ));
        if ($gr['ok']) { $labelN++; }
    }

    /* ⓓ السجلُّ المعياريُّ للتنقُّل */
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rt' LIMIT 1");
    if ($sid === '') { $maxSid++; $sid = 'SCR-' . str_pad((string) $maxSid, 4, '0', STR_PAD_LEFT); }
    $W("INSERT INTO nav_canonical (route, canonical_ar, level_no, level_name, group_name, sort_no,
                                   status, decision_state, application_state, decision_source,
                                   derivation, retirement_status, screen_id)
        VALUES ('$rt','" . $esc($s['ar']) . "',2,'العمليات','" . $esc(repair01_w15_group_ar($s['group'])) . "',"
                . (int) $s['sort'] . ",
                'APPROVED','APPROVED','DEPLOYED','RPR-W15 · المساحات والتقارير (2026-08-27)',
                'ترتيب دورة القيادة ودورة مساحة العمل في الحزمة','ACTIVE','" . $esc($sid) . "')
        ON DUPLICATE KEY UPDATE canonical_ar=VALUES(canonical_ar), group_name=VALUES(group_name),
          sort_no=VALUES(sort_no), status=VALUES(status), screen_id=VALUES(screen_id)");

    /* ⓔ مجموعةُ الدورةِ لا مجموعةُ الشقيق */
    if ($modId > 0) {
        $gkey = 'n9o_w15_' . strtolower(str_replace('-', '', $sid));
        $sib = $conn->query("SELECT n.role_id, n.door, g.stage_no, g.stage_title, g.display_order
                               FROM nav_items n
                               LEFT JOIN link_groups g ON g.id = n.group_id
                              WHERE n.route = '" . $esc($s['sibling']) . "' AND n.active = 1
                              GROUP BY n.role_id, n.door, g.stage_no, g.stage_title, g.display_order");
        while ($sib && $sx = $sib->fetch_assoc()) {
            $rid  = (int) $sx['role_id'];
            $code = $gkey . '_r' . $rid;
            $gid  = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            if ($gid === 0) {
                $W("INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order,
                                             stage_no, stage_title, is_active)
                    VALUES ('" . $esc(repair01_w15_group_ar($s['group'])) . "','" . $esc($code) . "',$rid,
                            '" . $esc($s['icon']) . "'," . ((int) $sx['display_order'] + 1) . ","
                            . (int) $sx['stage_no'] . ",'" . $esc((string) $sx['stage_title']) . "',1)");
                $gid = (int) $one("SELECT id FROM link_groups WHERE group_code = '" . $esc($code) . "' LIMIT 1");
            } else {
                $W("UPDATE link_groups SET name = '" . $esc(repair01_w15_group_ar($s['group'])) . "',
                        is_active = 1, stage_no = " . (int) $sx['stage_no'] . " WHERE id = $gid");
            }
            if ($gid <= 0) { continue; }
            $W("INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon,
                                       sort_order, permission_code, active)
                VALUES ($rid,'" . $esc($sx['door']) . "',$gid,$modId,'" . $esc($s['ar']) . "','$rt',
                        '" . $esc($s['icon']) . "'," . (int) $s['sort'] . ",'$rt',1)
                ON DUPLICATE KEY UPDATE label_ar=VALUES(label_ar), icon=VALUES(icon), group_id=VALUES(group_id),
                  sort_order=VALUES(sort_order), permission_code=VALUES(permission_code),
                  module_id=VALUES(module_id), active=1");
        }
        $navN += (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rt' AND active = 1");
    }

    /* ⓕ مصفوفةُ الدورةِ الحيّة — واسمُ الإدارةِ من الجسرِ لا مخترَعًا */
    if ($modId > 0) {
        $deptAr = (string) $one("SELECT legacy_name FROM repair01_dept_crosswalk
                                  WHERE canonical_code = '" . $esc($s['owner']) . "' ORDER BY id LIMIT 1");
        if ($deptAr === '') {
            $deptAr = (string) $one("SELECT name_ar FROM repair01_departments
                                      WHERE canonical_code = '" . $esc($s['owner']) . "' LIMIT 1");
        }
        if ($deptAr === '') { echo '  ⚠ لا جسرَ مسمًّى للإدارة ' . $s['owner'] . " — الصفُّ لا يُكتب\n"; }
        else {
            $W("DELETE FROM gov_screen_cycle
                 WHERE screen_file = '" . $esc($file) . "' AND inputs_note LIKE 'RPR-W15 %'");
            $W("INSERT INTO gov_screen_cycle
                (company_id, dept_name, layer_name, stage_order, stage_name, group_name, screen_title,
                 screen_file, inputs_note, output_doc, resp_role, next_state, consumers, fin_impact, stage_kind)
                VALUES (0,'" . $esc($deptAr) . "','" . $esc(repair01_w15_group_ar($s['group'])) . "','"
                        . (int) $s['sort'] . "','" . $esc(repair01_w15_group_ar($s['group'])) . "','"
                        . $esc(repair01_w15_group_ar($s['group'])) . "',
                        '" . $esc($s['ar']) . "','" . $esc($file) . "',
                        '" . $esc('RPR-W15 · متطلبات: ' . $s['req']) . "','" . $esc($s['doc']) . "',
                        '" . $esc($s['role']) . "','" . $esc($s['next']) . "','" . $esc($s['cons']) . "',
                        '" . $esc($s['fin']) . "','canonical')");
        }
    }

    /* ⓖ سجلُّ الشاشاتِ — بختمِ الموجةِ وبالحقولِ الاثنَي عشرَ كاملة.
         **والصنفُ `PROJECTION` بلا استثناء** — هذه المرحلةُ لا تملك حقيقة. */
    $guard = repair01_w15_guard_of($ROOT, $s['route']);
    $W("INSERT INTO repair01_screen_registry
        (screen_id, screen_file, route, route_rule, owner_code, owner_role, owner_rule,
         lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
         on_disk, origin, ghost_verdict, ghost_why, guard_kind, guard_evidence, w2_why, src_ref,
         canonical_label_ar, surface_kind, ownership_verdict, verdict_rule, verdict_at,
         action_guard, permission_policy, grain_ar, source_of_truth, state_model_ref)
        VALUES ('" . $esc($sid) . "','" . $esc($file) . "','$rt','W15_NEW_SURFACE_ROUTE',
                '" . $esc($s['owner']) . "','" . $esc($s['role']) . "','W15_REQUIREMENT_OWNER',
                'LIVE_UNREGISTERED','W15_GROWTH_OUTSIDE_STUDY_MATRIX','','','MENU_ITEM','NAV_ITEMS_ACTIVE',
                1,'W15','','',
                '" . $esc($guard['kind']) . "','" . $esc($guard['evidence']) . "',
                '" . $esc($s['ar']) . " (" . $esc($file) . ")','RPR-W15 · المساحات والتقارير',
                '" . $esc($s['ar']) . "','PROJECTION','EXECUTIVE_PROJECTION',
                '" . $esc('RPR-W15 §١ · اسقاط لا مصدر - يقرأ ' . $s['backing'] . ' عند ' . $s['bowner']) . "',
                NOW(),'ems_action_guard','ROLE_GRANT_VIA_MODULE',
                '" . $esc($s['doc']) . "','" . $esc($s['bowner']) . "','W15_STATE_MACHINES')
        ON DUPLICATE KEY UPDATE owner_code=VALUES(owner_code), owner_role=VALUES(owner_role),
          visibility_class=VALUES(visibility_class), guard_kind=VALUES(guard_kind),
          guard_evidence=VALUES(guard_evidence), origin=VALUES(origin), on_disk=1,
          route=VALUES(route), route_rule=VALUES(route_rule), lifecycle=VALUES(lifecycle),
          canonical_label_ar=VALUES(canonical_label_ar), surface_kind=VALUES(surface_kind),
          ownership_verdict=VALUES(ownership_verdict), verdict_rule=VALUES(verdict_rule),
          verdict_at=VALUES(verdict_at), action_guard=VALUES(action_guard),
          permission_policy=VALUES(permission_policy), grain_ar=VALUES(grain_ar),
          source_of_truth=VALUES(source_of_truth), state_model_ref=VALUES(state_model_ref)");
    $newN++;
}
printf("  أسطحُ نموٍّ مختومةٌ %d · بنودُ قائمةٍ نشِطة %d · منحٌ %d · مسمّياتٌ %d · بلا ملفٍّ %d%s · مصطدمٌ %d\n\n",
    $newN, $navN, $permN, $labelN, count($missing),
    $missing ? ' ⇐ ' . implode('، ', $missing) : '', count($collide));

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ب · مصفوفةُ الواجهة — **السطحُ المُصيَّرُ يلزمه صفٌّ فيها** (‏`U1`)
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ب مصفوفةُ الواجهةِ — صفٌّ لكلِّ سطحٍ مُصيَّر ──────────────────\n";
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
$mtxN = 0;
if (!is_file($MTX)) { echo "  ⚠ مصفوفةُ الواجهةِ غيرُ موجودة — التسجيلُ يُتخطّى\n"; }
elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    /* ⚠ **الصفوفُ الباقيةُ تُنقَل خامًّا لا يُعاد ترميزُها** (‏درسُ W13) */
    $lines = file($MTX, FILE_IGNORE_NEW_LINES);
    $hdr = array_shift($lines);
    $mine = array(); $keep = array(); $maxN = 0;
    foreach (repair01_w15_new_surfaces() as $s) { $mine[strtolower($s['route'])] = $s; }
    foreach ($lines as $ln) {
        if (trim($ln) === '') { continue; }
        $cells = str_getcsv($ln);
        if (!$cells || count($cells) < 2) { continue; }
        $maxN = max($maxN, (int) $cells[0]);
        if (isset($mine[strtolower(trim($cells[1]))])) { continue; }
        $keep[] = $ln;
    }
    $cell = function ($v) {
        $v = (string) $v;
        if ($v === '') { return '""'; }
        if (preg_match('/[",\s]/u', $v)) { return '"' . str_replace('"', '""', $v) . '"'; }
        return $v;
    };
    $rowsCsv = array();
    foreach (repair01_w15_new_surfaces() as $s) {
        $maxN++;
        $grp = repair01_w15_group_ar($s['group']);
        $depAr = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                               . $esc($s['owner']) . "'");
        if ($depAr === '') { $depAr = $s['owner']; }
        $srcAr = (string) $one("SELECT name_ar FROM repair01_departments WHERE canonical_code = '"
                               . $esc($s['bowner']) . "'");
        if ($srcAr === '') { $srcAr = $s['bowner']; }
        $def = 'تعرض ' . $s['ar'] . ' في دورة ' . $grp . ' لدى ' . $depAr
             . '. قراءة حية من سجل ' . $srcAr . ' ولا إدخال فيها.';
        $vals = array($maxN, $s['route'], $s['ar'], $s['ar'], '', '—', $def, $depAr,
            '2 — العمليات', $grp, $s['sort'], 'شاشةٌ مستقلة', 1, $s['cons'],
            'إسقاط ثبت غيابه فبني في موضعه المعياري', 'APPROVED',
            'ترتيبُ دورةِ العملِ في الحزمة — RPR-W15', '—', '—', 'ACTIVE', '—',
            $s['ar'], $grp, 'موضعُه من دورةِ العمل — قرارُ الورقة', $grp);
        $rowsCsv[] = implode(',', array_map($cell, $vals));
        $mtxN++;
    }
    file_put_contents($MTX, $hdr . "\n" . implode("\n", $keep) . "\n" . implode("\n", $rowsCsv) . "\n");
}
printf("  صفوفُ مصفوفةٍ مكتوبةٌ لأسطحِ الموجة %d\n\n", $mtxN);

/* ═══════════════════════════════════════════════════════════════════════════
   ③-ج · سجلُّ تصنيفِ المساحات — **الغيابُ ليس منعًا** (`NF-24` · `GAP-22`)
   ═══════════════════════════════════════════════════════════════════════════
   ◆ مسارٌ نشطٌ خارجَ سجلِّ التصنيفِ يُقرأ **مفتوحًا افتراضًا** — فالسقّاطةُ
     تُرسِّب على كلِّ جديدٍ غيرِ مصنَّف، وهي محقّة: سطحُ قيادةٍ يُقرأ مفتوحًا
     أخطرُ من سطحِ إدارة. والمساحةُ هي **الإدارةُ المالكةُ في السجلِّ المعياريّ**
     لا مساحةٌ مخترَعة.
   ═══════════════════════════════════════════════════════════════════════════ */
echo "③-ج تصنيفُ المساحاتِ — سطحٌ نشطٌ لا يُقرأ مفتوحًا افتراضًا ────────\n";
$spaceN = 0;
$hasSpace = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_space_appearances'");
if ($hasSpace === 0) { echo "  ⚠ سجلُّ المساحاتِ غيرُ موجود — التصنيفُ يُتخطّى\n"; }
elseif ($REPORT) { echo "  ↷ قياسٌ بلا كتابة\n"; }
else {
    foreach (repair01_w15_new_surfaces() as $s) {
        $rt = $esc($s['route']);
        $dep = (string) $one("SELECT name_ar FROM repair01_departments
                               WHERE canonical_code = '" . $esc($s['owner']) . "'");
        if ($dep === '') { $dep = $s['owner']; }
        $W("DELETE FROM gov_space_appearances WHERE route = '$rt' AND src_class = 'RPR-W15'");
        /* ⚠ **المفتاحُ هنا لا يتزايد ذاتيًّا** — والإدراجُ بلا `id` يصطدم
             بالصفرِ المكرَّر. فيُشتقُّ من أقصى القائمِ في كلِّ صفٍّ لا مرّةً. */
        $nextId = (int) $one("SELECT COALESCE(MAX(id), 0) + 1 FROM gov_space_appearances");
        $W("INSERT INTO gov_space_appearances
            (id, space_ar, space_kind, tab_ar, screen_ar, route, owner_dept_ar, owner_kind,
             src_class, src_ownership, src_decision, src_note, spaces_count,
             cls, ownership, decision, basis, rule_step, view_fields, updated_at)
            VALUES ($nextId,'" . $esc($dep) . "','DEPARTMENT','','" . $esc($s['ar']) . "','$rt',
                    '" . $esc($dep) . "','BUSINESS_DEPARTMENT',
                    'RPR-W15','VALID','CONFIRMED',
                    '" . $esc('سطح اسقاط مختوم W15 - قراءة حية بلا ادخال') . "',1,
                    'OWNED','VALID','CONFIRMED',
                    '" . $esc('المساحة هي الادارة المالكة للسطح في السجل المعياري (' . $s['owner'] . ')') . "',
                    1,'',NOW())");
        $spaceN++;
    }
}
printf("  أسطحٌ مصنَّفةٌ في سجلِّ المساحات %d\n\n", $spaceN);

/* ═══════════════════════════════════════════════════════════════════════════
   ④ دفترُ النطاق — خمسةٌ وأربعون متطلَّبًا بمِرساتِها
   ═══════════════════════════════════════════════════════════════════════════ */
echo "④ دفترُ النطاقِ — مِرساةٌ لكلِّ متطلَّب ───────────────────────────\n";
$scopeN = 0; $unproven = array();
$reqs = array();
$r = $conn->query("SELECT requirement_id, unit, group_name, surface FROM repair01_requirements WHERE stage_no = 15");
while ($r && ($x = $r->fetch_assoc())) { $reqs[$x['requirement_id']] = $x; }
foreach (repair01_w15_anchors() as $rid => $a) {
    if (!isset($reqs[$rid])) { echo "  ⚠ مِرساةٌ يتيمةٌ $rid\n"; continue; }
    $q = $reqs[$rid];
    $sid = $a['route'] === '' ? ''
         : (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '" . $esc($a['route']) . "' LIMIT 1");
    $pr = $a['route'] === '' ? array('verdict' => 'DEFERRED_OWNER', 'why' => $a['why'])
                             : repair01_w15_prove_anchor($conn, $ROOT, $a);
    if (!in_array($pr['verdict'], array('ANCHORED', 'DEFERRED_OWNER'), true)) { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
    $ownerMeasured = $a['route'] === '' ? ''
         : (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE route = '" . $esc($a['route']) . "' LIMIT 1");
    /* حكمُ الملكيّةِ: السطحُ يخدم مساحتَه — والنائبُ يُخدَم بسطحِ الرئيسِ نفسِه
       بنطاقٍ، فالمِرساةُ المشتركةُ ليست انحرافَ ملكيّة. */
    $ownVerdict = ($a['rule'] === 'W15_SAME_ENGINE_SCOPED') ? 'SHARED_ENGINE'
                : (($ownerMeasured === '' || $ownerMeasured === $a['space']) ? 'MATCH' : 'PROJECTION_OF_OWNER');
    $W("INSERT INTO repair01_w15_scope
        (requirement_id, unit, group_name, surface, space_code, anchor_screen_id, anchor_route,
         anchor_probe, backing_table, backing_owner, surface_kind, read_mode,
         owner_measured, owner_expected, owner_verdict, build_verdict, cycle_step, map_rule, map_why, src_ref)
        VALUES ('" . $esc($rid) . "','" . $esc($q['unit']) . "','" . $esc($q['group_name']) . "',
                '" . $esc($q['surface']) . "','" . $esc($a['space']) . "','" . $esc($sid) . "',
                '" . $esc($a['route']) . "','" . $esc($a['probe']) . "','" . $esc($a['backing']) . "',
                '" . $esc($a['bowner']) . "','" . ($a['route'] === '' ? '' : 'PROJECTION') . "',
                '" . ($a['route'] === '' ? '' : 'LIVE_REFERENCE') . "',
                '" . $esc($ownerMeasured) . "','" . $esc($a['space']) . "','" . $esc($ownVerdict) . "',
                '" . $esc($pr['verdict']) . "'," . (int) $a['step'] . ",
                '" . $esc($a['rule']) . "','" . $esc($a['why']) . "','RPR-W15 §٣')
        ON DUPLICATE KEY UPDATE anchor_screen_id=VALUES(anchor_screen_id), anchor_route=VALUES(anchor_route),
          backing_table=VALUES(backing_table), backing_owner=VALUES(backing_owner),
          surface_kind=VALUES(surface_kind), read_mode=VALUES(read_mode),
          owner_measured=VALUES(owner_measured), owner_verdict=VALUES(owner_verdict),
          build_verdict=VALUES(build_verdict), map_rule=VALUES(map_rule), map_why=VALUES(map_why)");
    $scopeN++;
}
printf("  متطلَّباتٌ في الدفتر %d من %d · مِرساةٌ لم تُثبَت %d%s\n\n",
    $scopeN, count($reqs), count($unproven), $unproven ? ' ⇐ ' . implode('، ', $unproven) : '');

/* ═══════════════════════════════════════════════════════════════════════════
   ⑤ السايدبارُ — سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑤ السايدبارُ — سبعُ خطواتٍ بحكمٍ وقاعدة ──────────────────────\n";
$routes = array();
foreach (repair01_w15_anchors() as $a) { if ($a['route'] !== '') { $routes[$a['route']] = $a; } }
$sbN = 0;
foreach ($routes as $rt => $a) {
    $rtE = $esc($rt);
    $sid = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    if ($sid === '') { continue; }
    $owner = (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");

    /* ① تعطيلُ غيرِ المعتمدِ بعذرٍ مكتوب — ولا حذف */
    $inactive = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '$rtE' AND active = 0");
    $s1 = $inactive > 0 ? 'HAS_DISABLED_WITH_REASON' : 'ALL_ACTIVE';

    /* ② الاسمُ على السجلِّ المعياريّ — ويُصحَّح لا يُقاس فقط */
    $canAr = (string) $one("SELECT canonical_ar FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    $live  = (string) $one("SELECT label_ar FROM nav_items WHERE route = '$rtE' ORDER BY id LIMIT 1");
    if ($canAr !== '' && $live !== '' && $canAr !== $live) {
        $W("UPDATE nav_items SET label_ar = '" . $esc($canAr) . "' WHERE route = '$rtE'");
        $live = $canAr;
    }
    $s2 = ($canAr === '' || $canAr === $live) ? 'LABEL_MATCH' : 'LABEL_DRIFT';

    /* ③ المجموعةُ على مجموعةِ الدورة */
    $grpCanon = repair01_w15_group_ar($a['group']);
    $grpLive  = (string) $one("SELECT g.name FROM nav_items n LEFT JOIN link_groups g ON g.id = n.group_id
                                WHERE n.route = '$rtE' AND n.active = 1 ORDER BY n.id LIMIT 1");
    $s3 = ($grpLive === '' || $grpLive === $grpCanon) ? 'GROUP_MATCH' : 'GROUP_LEGACY_KEPT';

    /* ④ الترتيبُ على دورةِ العملِ لا على الأبجديّة */
    $wantSort = (int) $a['step'];
    $W("UPDATE nav_items SET sort_order = $wantSort WHERE route = '$rtE'");
    $W("UPDATE nav_canonical SET sort_no = $wantSort WHERE route = '$rtE'");
    $s4 = 'CYCLE_ORDER_APPLIED';

    /* ⑤ الأبُ والتبويب — والقرارُ يحدّد الموضع */
    $parent = (string) $one("SELECT parent_screen_id FROM repair01_screen_registry WHERE route = '$rtE' LIMIT 1");
    $s5 = $parent !== '' ? 'TAB_CHILD_OF_PARENT' : 'MENU_ITEM_STANDALONE';

    /* ⑥ الظهورُ بالصلاحيةِ لا بالإخفاء — ولكلِّ سطحٍ حارسُ عرضٍ خادميّ */
    $perm = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                         WHERE m.code = '$rtE' AND rp.can_view = 1");
    $g = repair01_w15_guard_of($ROOT, $rt);
    $s6 = ($g['kind'] !== 'NONE' && $perm > 0) ? 'GUARDED_AND_GRANTED'
        : ($g['kind'] === 'NONE' ? 'NO_SERVER_GUARD' : 'NO_GRANT');

    /* ⑦ الربطُ بالمُعرِّفِ المعياريّ — **ملءُ `screen_id` لا وجودُ صفّ** (‏درسُ W14 ④) */
    $navSid = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '$rtE' LIMIT 1");
    if ($navSid === '' || $navSid === null) {
        $W("UPDATE nav_canonical SET screen_id = '" . $esc($sid) . "' WHERE route = '$rtE'");
        $navSid = $sid;
    }
    $s7 = ($navSid === $sid) ? 1 : 0;

    $W("INSERT INTO repair01_w15_sidebar
        (screen_id, route, owner_code, s1_verdict, s1_rule, s2_label_live, s2_label_canon, s2_verdict, s2_rule,
         s3_group_live, s3_group_canon, s3_verdict, s3_rule, s4_order_src, s4_order_no, s4_cycle_step,
         s4_verdict, s4_rule, s5_parent, s5_verdict, s5_rule, s5_why, s6_visibility, s6_perm_rows,
         s6_guard_kind, s6_verdict, s6_rule, s7_linked, s7_verdict, s7_rule)
        VALUES ('" . $esc($sid) . "','$rtE','" . $esc($owner) . "',
                '" . $esc($s1) . "','W15_SB1_DISABLE_WITH_REASON',
                '" . $esc($live) . "','" . $esc($canAr) . "','" . $esc($s2) . "','W15_SB2_CANONICAL_LABEL',
                '" . $esc($grpLive) . "','" . $esc($grpCanon) . "','" . $esc($s3) . "','W15_SB3_CYCLE_GROUP',
                'W15_CYCLE_STEP'," . $wantSort . "," . (int) $a['step'] . ",
                '" . $esc($s4) . "','W15_SB4_WORKFLOW_ORDER',
                '" . $esc($parent) . "','" . $esc($s5) . "','W15_SB5_PARENT_TAB','القرار يحدد الموضع',
                'MENU_ITEM'," . $perm . ",'" . $esc($g['kind']) . "','" . $esc($s6) . "','W15_SB6_PERMISSION_NOT_HIDE',
                " . $s7 . ",'" . ($s7 ? 'LINKED_TO_CANONICAL_ID' : 'NOT_LINKED') . "','W15_SB7_CANONICAL_SCREEN_ID')
        ON DUPLICATE KEY UPDATE route=VALUES(route), owner_code=VALUES(owner_code),
          s1_verdict=VALUES(s1_verdict), s2_label_live=VALUES(s2_label_live),
          s2_label_canon=VALUES(s2_label_canon), s2_verdict=VALUES(s2_verdict),
          s3_group_live=VALUES(s3_group_live), s3_verdict=VALUES(s3_verdict),
          s4_order_no=VALUES(s4_order_no), s4_verdict=VALUES(s4_verdict),
          s5_parent=VALUES(s5_parent), s5_verdict=VALUES(s5_verdict),
          s6_perm_rows=VALUES(s6_perm_rows), s6_guard_kind=VALUES(s6_guard_kind),
          s6_verdict=VALUES(s6_verdict), s7_linked=VALUES(s7_linked), s7_verdict=VALUES(s7_verdict),
          measured_at=NOW()");
    $sbN++;
}
printf("  أسطحٌ بسبعِ خطواتٍ %d من %d\n\n", $sbN, count($routes));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑥ محورا الرؤيةِ والسلطة — مفصولانِ بقيدٍ في القاعدة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑥ الرؤيةُ لا تساوي السلطة ───────────────────────────────────\n";
$AXES = array(
    array('ceo_visibility', 'EX-CEO', 'الرئيس التنفيذي',
          'يرى كل الإدارات والمشروعات والمواقع بلا حصر',
          'يقرر فيما تسنده له قاعدة سلطة نافذة ومصفوفة اعتماد بالقيمة',
          'gov_authority_limits', 'gov_delegations',
          'الرؤية شاملة والسلطة محصورة بقاعدتها وهذا نص الأمر الأول البند 27'),
    array('vp_visibility', 'EX-DVP', 'نائب الرئيس',
          'يرى الشركة كاملة قراءة ونطاقه افتراضيا',
          'يقرر داخل نطاق تكليفه وسقفه ولا يقرر خارجه',
          'org_assignments', 'gov_delegations',
          'الرؤية أوسع من السلطة وظيفة لا وصفا وعلم صريح يفصل القراءة عن القرار'),
    array('my_visibility', 'WS-MY', 'صاحب الحساب',
          'يرى صفوفه ومحيطه المباشر بحسب دوره',
          'يطلق طلبا ويؤكد إغلاق بلاغه ولا يعتمد شيئا',
          'gov_authority_limits', 'gov_delegations',
          'مساحة عملي مطلق وإسقاط ولا تصير مالكة ولا معتمدة'),
);
$axN = 0;
foreach ($AXES as $x) {
    $W("INSERT INTO repair01_w15_scope_axis
        (axis_key, space_code, role_key, visibility_rule, authority_rule, authority_src, delegation_src, why)
        VALUES ('" . $esc($x[0]) . "','" . $esc($x[1]) . "','" . $esc($x[2]) . "','" . $esc($x[3]) . "',
                '" . $esc($x[4]) . "','" . $esc($x[5]) . "','" . $esc($x[6]) . "','" . $esc($x[7]) . "')
        ON DUPLICATE KEY UPDATE visibility_rule=VALUES(visibility_rule),
          authority_rule=VALUES(authority_rule), authority_src=VALUES(authority_src), why=VALUES(why)");
    $axN++;
}
printf("  محاورُ مفصولةٌ %d\n\n", $axN);

/* ═══════════════════════════════════════════════════════════════════════════
   ⑦ العتبات — من السجلِّ وحدَه
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑦ العتباتُ — من السجلِّ لا من الشيفرة ─────────────────────────\n";
$TH = array(
    array('exec.read_cap.rows', 'سقف صفوف سطح القراءة', 'CONFIG_PENDING', null, 'Policy Registry',
          '', 'RPR-W15 §٦ · سقف عرض لا عتبة قرار'),
    array('exec.approval.ceo_level_amount', 'قيمة بلوغ مستوى الرئيس', 'CONFIG_PENDING', null,
          'AAM', '', 'RPR-W15 §٦ · DEC-OPEN القيم العددية'),
    array('exec.approval.vp_level_amount', 'قيمة بلوغ مستوى النائب', 'CONFIG_PENDING', null,
          'AAM', '', 'RPR-W15 §٦ · DEC-OPEN القيم العددية'),
    array('exec.escalation.overdue_days', 'مهلة تصعيد القرار المتأخر', 'CONFIG_PENDING', null,
          'Policy Registry', '', 'RPR-W15 §٦'),
    array('exec.reserved_matters.value', 'قيمة المسائل المحجوزة', 'CONFIG_PENDING', null,
          'Policy Registry', '', 'RPR-W15 §٦ · نص المالك: Reserved Matters values'),
    array('ws.request.sla_hours', 'مهلة الطلب المطلق', 'CONFIG_PENDING', null,
          'Policy Registry', '', 'RPR-W15 §٦'),
);
$thN = 0;
foreach ($TH as $t) {
    $W("INSERT INTO repair01_w15_thresholds (th_key, title_ar, state, value_num, registry, owner_text, src_ref)
        VALUES ('" . $esc($t[0]) . "','" . $esc($t[1]) . "','" . $esc($t[2]) . "',"
             . ($t[3] === null ? 'NULL' : (float) $t[3]) . ",'" . $esc($t[4]) . "','" . $esc($t[5]) . "','" . $esc($t[6]) . "')
        ON DUPLICATE KEY UPDATE title_ar=VALUES(title_ar), state=VALUES(state),
          value_num=VALUES(value_num), registry=VALUES(registry), owner_text=VALUES(owner_text)");
    $thN++;
}
printf("  عتباتٌ مسجَّلةٌ %d · معتمَدةٌ بنصِّ المالك %d · منتظرةٌ %d\n\n",
    $thN, (int) $one("SELECT COUNT(*) FROM repair01_w15_thresholds WHERE state = 'OWNER_APPROVED'"),
    (int) $one("SELECT COUNT(*) FROM repair01_w15_thresholds WHERE state = 'CONFIG_PENDING'"));

/* ═══════════════════════════════════════════════════════════════════════════
   ⑧ مسحُ الكتابةِ من مساحاتِ الموجة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "⑧ مسحُ الكتابةِ من مساحاتِ الموجة ─────────────────────────────\n";
$W("DELETE FROM repair01_w15_space_writes");
$wr = repair01_w15_scan_writes($ROOT);
$forb = 0;
foreach ($wr as $x) {
    if ($x['verdict'] === 'FORBIDDEN') { $forb++; }
    $W("INSERT INTO repair01_w15_space_writes
        (file_path, space_code, verb, table_name, table_owner, line_no, verdict, why)
        VALUES ('" . $esc($x['file']) . "','" . $esc($x['space']) . "','" . $esc($x['verb']) . "',
                '" . $esc($x['table']) . "','','" . (int) $x['line'] . "','" . $esc($x['verdict']) . "',
                '" . $esc($x['why']) . "')");
}
printf("  كتاباتٌ مقروءةٌ من الشيفرة %d · ممنوعةٌ %d · في سجلِّ المساحةِ نفسِها %d\n\n",
    count($wr), $forb, count($wr) - $forb);

echo "────────────────────────────────────────────────────────────\n";
echo "تمَّ الإنزال. شغّلْ: php tools/repair01_w15_gate.php\n";
exit(0);
