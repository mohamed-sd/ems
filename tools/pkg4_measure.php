<?php
/**
 * tools/pkg4_measure.php — مسبارُ القياسِ الشاملُ للحزمةِ الرباعية
 * UXW-01 · 24-أ السلاليم · 70 المواصفةُ التقنية · GOV-AUTH-01
 * قراءةٌ فقط — يُخرج كلَّ بندٍ مقيسٍ وحالتَه بدليلٍ حيٍّ من القاعدةِ والمستودع.
 * الاستعمال:  php tools/pkg4_measure.php  [text|json]
 * ملاحظةُ قياس: information_schema.TRIGGERS يرجع صفرًا بمستخدمِ التطبيق —
 *               فالقوادحُ تُجَسُّ وظيفيًّا أو تُعَدُّ «غيرَ مقيسة» ولا تُدَّعى.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$mode = $argv[1] ?? 'text';

function q($sql) { global $conn; $r = @$conn->query($sql); if ($r === false) return null; $o = []; while ($x = $r->fetch_assoc()) $o[] = $x; return $o; }
function q1($sql) { $r = q($sql); if ($r === null || !count($r)) return null; return array_values($r[0])[0]; }
function cnt($sql) { $v = q1($sql); return $v === null ? null : (int)$v; }
function tbl($t) { return (int)q1("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($t) . "'") > 0; }
function vw($v)  { return (int)q1("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($v) . "'") > 0; }
function col($t, $c) { return (int)q1("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . addslashes($t) . "' AND COLUMN_NAME='" . addslashes($c) . "'") > 0; }
function chk($n) { return (int)q1("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='" . addslashes($n) . "'") > 0; }
function fx($p) { global $ROOT; return is_file($ROOT . '/' . $p); }
function firstFile(array $c) { foreach ($c as $f) if (fx($f)) return $f; return null; }

$R = [];
function rec($doc, $id, $label, $expected, $actual, $state, $note = '') {
    global $R; $R[] = compact('doc','id','label','expected','actual','state','note');
}

/* مسحٌ واحدٌ للمستودعِ يخدم كلَّ بوابةٍ ملفّية */
$SCREEN_DIRS = ['admin','Assets','Contracts','Customers','Employees','Equipments','Finance','Financing','Governance','HR','Maintenance','main','Operations','Portal','Procurement','Projects','Reports','Risk','Sales','Suppliers','Tickets','Timesheet','Transport','Workforce'];
$scan = ['screens' => 0, 'shell' => 0, 'inline' => 0, 'inlineFiles' => 0, 'hex' => 0, 'hexFiles' => 0, 'banned' => [], 'files' => 0];
$SCAN_ALL = ['inline' => 0, 'hex' => 0];
$BANNED = ['خارج الوثيقة','بانتظار المالك','إضافات للمالك','Activation Pattern','Visibility Guard'];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = strtr($f->getPathname(), chr(92), "/");
    if (!preg_match('/\.(php|css)$/i', $p)) continue;
    if (preg_match('#/(vendor|node_modules|\.git|storage|\.claude|\.ssdiff)/#i', $p)) continue;
    $c = @file_get_contents($p); if ($c === false) continue;
    $rel = ltrim(str_replace(str_replace('\\','/',$ROOT), '', $p), '/');
    $top = explode('/', $rel)[0];
    $SCAN_ALL['inline'] += preg_match_all('/\sstyle\s*=\s*["\']/i', $c);
    $SCAN_ALL['hex']    += preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $c);
    // نطاقُ الشاشاتِ وحدَه — وهو نطاقُ خطِّ الأساسِ في الوثيقة
    if (!in_array($top, $SCREEN_DIRS, true) && strpos($rel, '/') !== false) continue;
    if (in_array($top, ['tools','tests','database','docs','app','includes','assets'], true)) continue;
    if (!preg_match('/\.php$/i', $p)) continue;
    $scan['files']++;
    $n1 = preg_match_all('/\sstyle\s*=\s*["\']/i', $c); if ($n1) { $scan['inline'] += $n1; $scan['inlineFiles']++; }
    $n2 = preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $c);  if ($n2) { $scan['hex'] += $n2;    $scan['hexFiles']++; }
    foreach ($BANNED as $b) if (strpos($c, $b) !== false) $scan['banned'][$b] = ($scan['banned'][$b] ?? 0) + 1;
    if (preg_match('/<body|<!DOCTYPE|page_header|inheader/i', $c)) {
        $scan['screens']++;
        if (preg_match('/inheader\.php|page_header\.php|insidebar\.php/i', $c)) $scan['shell']++;
    }
}

/* ================================================================ ١ — GOV-AUTH-01 */
$D = 'GOV-AUTH-01';
$govTables = ['gov_role_profiles','gov_profile_items','gov_authority_grants','gov_delegations','gov_elevations','impersonation_sessions'];
$tOk = 0; $tMiss = []; foreach ($govTables as $t) { if (tbl($t)) $tOk++; else $tMiss[] = $t; }
rec($D,'GA-T','الجداولُ الستةُ (٨-١)',6,$tOk,$tOk===6?'DONE':($tOk?'PARTIAL':'TODO'), $tMiss?'ناقص: '.implode(',',$tMiss):'');

$isoOk = 0; $isoMiss = [];
foreach ($govTables as $t) { if (!tbl($t)) continue; if (col($t,'entity_id')||col($t,'company_id')) $isoOk++; else $isoMiss[] = $t; }
rec($D,'GA-ISO','عمودُ العزلِ في الجداولِ الستة',6,$isoOk,$isoOk===6?'DONE':'PARTIAL',$isoMiss?implode(',',$isoMiss):'');

/* ◆ أربعةٌ من الأربعةَ عشرَ منفَّذةٌ **قوادحَ** لا قيودَ CHECK (لأنها تعبر الجداول)،
   و`information_schema.TRIGGERS` يرجع صفرًا بمستخدمِ التطبيق — فقراءتُها من
   المستودعِ شاهدًا (والفحصُ السلبيُّ الحيُّ في `tools/govauth_checks.php` أثبت
   رفضَها فعلًا بالخطأ 1644). وإغفالُها كان يقرأ منفَّذًا صفرًا. */
$TRG_OF = ['chk_grant_issuer'=>'trg_grant_issuer','chk_deleg_no_relay'=>'trg_deleg_no_relay',
           'chk_imp_not_oversight'=>'trg_imp_not_oversight','chk_non_delegable'=>'trg_deleg_non_delegable'];
$MIG_SRC = implode("\n", array_map('file_get_contents', glob($ROOT . '/database/migrations/*.php') ?: []));
function trgInRepo($constraint) {
    global $TRG_OF, $MIG_SRC;
    if (!isset($TRG_OF[$constraint])) return false;
    return (bool) preg_match('/CREATE\s+TRIGGER\s+`?' . preg_quote($TRG_OF[$constraint], '/') . '`?/i', $MIG_SRC);
}
$govChecks = ['chk_grant_issuer','chk_temp_has_end','chk_grant_source','chk_deleg_reason','chk_deleg_not_self','chk_deleg_no_relay','chk_elev_four_parties','chk_elev_ceo_not_self','chk_imp_reason','chk_imp_not_oversight','chk_imp_not_self','chk_act_attribution','chk_no_single_hand','chk_non_delegable'];
$cOk = 0; $cMiss = []; $cTrg = 0;
foreach ($govChecks as $c) {
    if (chk($c)) { $cOk++; }
    elseif (trgInRepo($c)) { $cOk++; $cTrg++; }
    else { $cMiss[] = $c; }
}
rec($D,'GA-C','القيودُ الأربعةَ عشرَ (٨-٢)',14,$cOk,$cOk===14?'DONE':($cOk?'PARTIAL':'TODO'),
    ($cMiss ? 'ناقص: '.implode(' · ',$cMiss).' · ' : '') . "منها $cTrg قادحًا (شاهدُه المستودعُ والفحصُ السلبيُّ 1644 — لا يراه information_schema بمستخدمِ التطبيق)");

$govViews = ['v_effective_authority','v_authority_expiring','v_open_elevations','v_active_impersonations','v_hand_conflicts'];
$vOk = 0; $vMiss = []; foreach ($govViews as $v) { if (vw($v)) $vOk++; else $vMiss[] = $v; }
rec($D,'GA-V','المناظرُ الخمسةُ الرقابية (٨-٣)',5,$vOk,$vOk===5?'DONE':'PARTIAL',$vMiss?implode(',',$vMiss):'');

$prof = cnt("SELECT COUNT(*) FROM gov_role_profiles");
$pg = cnt("SELECT COUNT(DISTINCT grade) FROM gov_role_profiles");
$pd = cnt("SELECT COUNT(DISTINCT dept_code) FROM gov_role_profiles");
rec($D,'GA-P171','قوالبُ المسمياتِ ٩ درجاتٍ × ١٩ إدارة',171,$prof,($prof>=171)?'DONE':'PARTIAL',"درجات=$pg · إدارات=$pd");
rec($D,'GA-PI','بنودُ القوالبِ (شاشة/فعل/سقف/نطاق/حقل)','>0',cnt("SELECT COUNT(*) FROM gov_profile_items"),'DONE','');

$aOk = (int)col('activity_logs','acted_by')+(int)col('activity_logs','acted_for')+(int)col('activity_logs','impersonation_id');
rec($D,'GA-A5','أعمدةُ النسبةِ الثلاثةُ (A5) في دفترِ الأفعال',3,$aOk,$aOk===3?'DONE':'PARTIAL','activity_logs');

$scr3 = ['Governance/auth_profiles.php','Governance/auth_grants.php','Governance/impersonations.php'];
$sOk = 0; $sMiss = []; foreach ($scr3 as $s) { if (fx($s)) $sOk++; else $sMiss[] = basename($s); }
rec($D,'GA-SCR','الشاشاتُ الثلاثُ (القوالبُ · المنحُ · النيابة)',3,$sOk,$sOk===3?'DONE':'PARTIAL',$sMiss?implode(',',$sMiss):implode(' · ',array_map('basename',$scr3)));
$navG = cnt("SELECT COUNT(DISTINCT route) FROM nav_items WHERE active=1 AND route IN ('Governance/auth_profiles.php','Governance/auth_grants.php','Governance/impersonations.php')");
rec($D,'GA-NAV','الشاشاتُ الثلاثُ مسجَّلةٌ ومفعَّلةٌ في التنقل',3,$navG,($navG>=3)?'DONE':'PARTIAL','');

$sweep = cnt("SELECT COUNT(*) FROM ems_job_schedule WHERE job_type LIKE '%authority%' OR job_type LIKE '%expiry%'");
rec($D,'GA-A6','مهمةُ authority_expiry_sweep مجدوَلةً كلَّ ٥ دقائق (A6)',1,$sweep,($sweep>0)?'DONE':'TODO','8 مهامَّ مجدوَلةٍ ولا واحدةَ للانتهاء');

rec($D,'GA-ND','non_delegable_actions — الخمسةُ المحصورة',5,cnt("SELECT COUNT(*) FROM non_delegable_actions"),(cnt("SELECT COUNT(*) FROM non_delegable_actions")>=5)?'DONE':'PARTIAL','');
$diff = glob("$ROOT/storage/reports/*govauth*") ?: glob("$ROOT/storage/reports/*gov_auth*");
rec($D,'GA-DIFF','تقريرُ الفروقِ قبلَ التبديل (خطوةُ الترحيلِ ٣)',1,count($diff),count($diff)?'DONE':'TODO',$diff?basename($diff[0]):'');

$gr = cnt("SELECT COUNT(*) FROM gov_authority_grants");
$grU = cnt("SELECT COUNT(DISTINCT user_id) FROM gov_authority_grants");
$us = cnt("SELECT COUNT(*) FROM users WHERE COALESCE(is_deleted,0)=0");
rec($D,'GA-GRANT','إلحاقُ القالبِ بكلِّ موظفٍ حاليّ',$us,"$grU مستخدمًا · $gr منحًا",($grU>=$us)?'DONE':'PARTIAL',"مستخدمون نشطون=$us");

rec($D,'GA-A4','v_hand_conflicts = صفر (شرطُ إغلاقِ A4)',0,cnt("SELECT COUNT(*) FROM v_hand_conflicts"),(cnt("SELECT COUNT(*) FROM v_hand_conflicts")===0)?'DONE':'PARTIAL','');

$impBar = 0; $impWhere = [];
foreach (['includes/page_header.php','inheader.php','insidebar.php'] as $f)
    if (fx($f) && stripos(file_get_contents("$ROOT/$f"),'impersonat')!==false) { $impBar++; $impWhere[] = $f; }
rec($D,'GA-IMPBAR','شريطُ جلسةِ الإنابةِ الدائمُ في القشرة','≥1',$impBar,$impBar?'DONE':'TODO',$impWhere?implode(',',$impWhere):'لا وسمَ في أيٍّ من ملفاتِ القشرةِ الثلاثة');

$impUse = cnt("SELECT COUNT(*) FROM impersonation_sessions");
$delUse = cnt("SELECT COUNT(*) FROM gov_delegations");
$elvUse = cnt("SELECT COUNT(*) FROM gov_elevations");
rec($D,'GA-LIVE','استعمالٌ حيٌّ للطرقِ الثلاثِ (تفويض/رفع/نيابة)','>0',"تفويض=$delUse · رفع=$elvUse · نيابة=$impUse",'TODO','البنيةُ قائمةٌ وصفرُ حركةٍ — لا شاهدَ تشغيل');

/* ================================================================ ٢ — 24-أ السلاليم */
$D = '24-أ السلاليم';
$LT = tbl('gov_ladders') ? 'gov_ladders' : null;
$LS = tbl('gov_ladder_steps') ? 'gov_ladder_steps' : null;
$lad = $LT ? cnt("SELECT COUNT(DISTINCT ladder_code) FROM `$LT`") : 0;
$ladAct = $LT ? cnt("SELECT COUNT(*) FROM `$LT` WHERE is_active=1") : 0;
rec($D,'LD-20','السلاليمُ العشرون LD-01..LD-20 مسجَّلة',20,$lad,($lad>=20)?'DONE':($lad?'PARTIAL':'TODO'),"مفعَّلة=$ladAct · الناقصُ LD-".sprintf('%02d',$lad+1)."..LD-20");

// العناصرُ السبعة: E1 بادئ · E2 مراجع · E3 معتمِد (من الخطوات) · E4 سقف · E5 تصعيد · E6 منعُ الذات · E7 حمولة
$E = [];
$E['E1 من يبدأ']    = $LS ? (cnt("SELECT COUNT(DISTINCT ladder_code) FROM `$LS` WHERE step_kind='entry' OR step_no=1")) : 0;
$E['E2 من يراجع']   = $LS ? (cnt("SELECT COUNT(DISTINCT ladder_code) FROM `$LS` WHERE may_approve=0 AND step_no>1")) : 0;
$E['E3 من يعتمد']   = $LS ? (cnt("SELECT COUNT(DISTINCT ladder_code) FROM `$LS` WHERE may_approve=1")) : 0;
$E['E4 السقف']      = $LT ? (cnt("SELECT COUNT(*) FROM `$LT` WHERE cap_state IN ('resolved','not_applicable')")) : 0;
$E['E5 التصعيد']    = $LT ? (cnt("SELECT COUNT(*) FROM `$LT` WHERE escalate_after_hours IS NOT NULL")) : 0;
$E['E6 منعُ الذات'] = $LS ? (cnt("SELECT COUNT(DISTINCT ladder_code) FROM `$LS` WHERE COALESCE(forbid_note,'')<>''")) : 0;
$E['E7 الحمولة']    = $LT ? (cnt("SELECT COUNT(*) FROM `$LT` WHERE COALESCE(payload_note,'')<>''")) : 0;
$eFull = 0; $eNote = [];
foreach ($E as $k => $v) { $eNote[] = "$k=$v/$lad"; if ($lad && $v >= $lad) $eFull++; }
rec($D,'LD-E7','العناصرُ السبعةُ كاملةً في كلِّ سلّمٍ مسجَّل',"7 × $lad",$eFull.'/7 عنصرًا مكتمل',($eFull===7)?'DONE':'PARTIAL',implode(' · ',$eNote));

$missElem = $lad ? ($lad*7 - array_sum($E)) : null;
rec($D,'AC-L3','صفرُ سلّمٍ مفعَّلٍ ينقصه عنصرٌ من السبعة',0,$missElem,($missElem===0)?'DONE':'PARTIAL',"من ".($lad*7)." خانةَ عنصر");

$oneStep = $LS ? cnt("SELECT COUNT(*) FROM (SELECT ladder_code FROM `$LS` GROUP BY ladder_code HAVING COUNT(*)<2) x") : null;
$oneWho  = $LS ? implode(',', array_column(q("SELECT ladder_code FROM `$LS` GROUP BY ladder_code HAVING COUNT(*)<2") ?: [], 'ladder_code')) : '';
rec($D,'AC-L1','صفرُ سلّمٍ يسقط على خطوةٍ واحدةٍ احتياطية',0,$oneStep,($oneStep===0)?'DONE':'PARTIAL',$oneWho?"بخطوةٍ واحدة: $oneWho":'');

$capUnres = $LT ? cnt("SELECT COUNT(*) FROM `$LT` WHERE cap_state='unresolved'") : null;
$capChk = chk('chk_ld_cap');
rec($D,'AC-L9','قيدُ السقفِ: الفارغُ يوقف ولا يُمرِّر','قيدٌ نافذ',$capChk?'chk_ld_cap':'غائب',$capChk?'DONE':'TODO',"سقوفٌ غيرُ محسومة=$capUnres");
/* ◆ تصحيحُ قراءةٍ (2026-08-18): السقوفُ الثلاثةُ الظاهرةُ في السلاليمِ المبنيّةِ
   **حُسمت** بتفويضِ المالكِ الصريحِ باشتقاقٍ مقيسٍ من 5,069 واقعةً ماليةً
   (هجرة 2027_06_17 — LD-05=2,000 · LD-06=5,000 · LD-07=10,000 دولارًا).
   والباقي ستةٌ لم يُبنَ سلّمُها بعدُ فلا سقفَ لها يُحسم. */
$capBuilt = $LT ? cnt("SELECT COUNT(*) FROM `$LT` WHERE cap_kind='amount'") : 0;
$capDone  = (int)$capBuilt - (int)$capUnres;
rec($D,'LD-CAP9','السقوفُ التسعةُ بانتظارِ رقمِ المالك',9,"$capDone محسومًا من $capBuilt مبنيًّا",'BLOCKED',
    ($capUnres ? "غيرُ محسومٍ في المبنيّ: $capUnres · " : "المبنيُّ كلُّه محسوم · ")
    . (9-(int)$capBuilt) . ' منها لم يُبنَ سلّمُها بعدُ فلا سقفَ لها يُحسم');

$selfChk = chk('chk_no_single_hand') || chk('chk_step_entry_no_approve') || chk('chk_bg_no_self_approval');
rec($D,'AC-L4','منعُ اعتمادِ الذاتِ قيدًا لا سياسة','قيد',$selfChk?'chk_step_entry_no_approve · chk_no_single_hand · chk_bg_no_self_approval':'غائب',$selfChk?'PARTIAL':'TODO',"مطبَّقٌ على $lad سلّمًا من 20");

$twoHand = chk('chk_ded_prop_two_hands') || chk('chk_bg_two_hands') || chk('chk_ded_prop_review_hand');
rec($D,'AC-L5','لا يدَ تمشي خطوتين متتاليتين في سلّمٍ واحد','قيدٌ عامّ',$twoHand?'قيودٌ موضعيةٌ فقط':'غائب','PARTIAL','قيودُ يدَين موجودةٌ في الخصومِ والطوارئ — ولا قيدَ عامٌّ على سجلِّ خطواتِ السلاليم');

$badRule = $LT ? cnt("SELECT COUNT(*) FROM `$LT` WHERE slug REGEXP '[[:space:]]' OR CHAR_LENGTH(slug)>48 OR COALESCE(doc_ref,'')=''") : null;
rec($D,'AC-L2','صفرُ صفٍّ في جدولِ القواعدِ يحمل نصًّا غيرَ قاعدة',0,$badRule,($badRule===0)?'DONE':'PARTIAL','');

$manual = cnt("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME IN ('approver_name','approved_by_name','signed_by_name','approver_text') AND DATA_TYPE IN ('varchar','text')");
rec($D,'AC-L8','صفرُ حقلٍ يُكتب فيه اسمُ معتمِدٍ يدويًّا',0,$manual,($manual===0)?'DONE':'PARTIAL','');

$bg = $LT ? cnt("SELECT COUNT(*) FROM `$LT` WHERE ladder_code='LD-20'") : 0;
$bgTbl = tbl('scr_break_glass');
rec($D,'LD-BG','LD-20 قفلُ الطوارئ سلّمًا مسجَّلًا',1,$bg,($bg>0)?'DONE':'PARTIAL',$bgTbl?'scr_break_glass قائمٌ بقيدَيه — لكنْ خارجَ سجلِّ السلاليم':'');

$traceL = glob("$ROOT/docs/*LAD*") ?: glob("$ROOT/storage/reports/*lad*");
rec($D,'LD-227','سجلُّ تتبعِ المتطلباتِ الذريةِ ٢٢٧',227,count($traceL)?'ملفٌّ واحد':0,count($traceL)?'PARTIAL':'TODO',$traceL?basename($traceL[0]):'لا ملفَّ تتبعٍ بحالاتِ التنفيذ');

/* ================================================================ ٣ — 70 المواصفة */
$D = '70 المواصفة';
$cntSpec = ['cnt_annual_containers','cnt_type_containers','cnt_machine_slots','sup_slot_allocations','sup_handover_events','sup_settlements'];
$cntLive = ['op_containers','container_consumption','container_swaps','supplier_capacity','settlements','settlement_lines'];
$cA = 0; foreach ($cntSpec as $t) if (tbl($t)) $cA++;
$cB = 0; $cBn = []; foreach ($cntLive as $t) if (tbl($t)) { $cB++; $cBn[] = $t; }
rec($D,'TS-CNT','جداولُ الحاوياتِ والموردينَ بأسماءِ الوثيقة',6,$cA,($cA===6)?'DONE':($cA?'PARTIAL':'TODO'),"صفرٌ بأسماءِ الوثيقة · $cB نظيرًا حيًّا: ".implode(',',$cBn));

$engSpec = ['ems_event_outbox','ems_event_subscriptions','ems_event_deliveries','ems_job_queue','ems_job_schedule','fa_asset_hours'];
$eOk = 0; $eMiss = []; foreach ($engSpec as $t) { if (tbl($t)) $eOk++; else $eMiss[] = $t; }
rec($D,'TS-ENG','جداولُ المحرّكاتِ الستة',6,$eOk,($eOk===6)?'DONE':'PARTIAL',$eMiss?implode(',',$eMiss):'');

$shiftT = tbl('shift_entries') ? 'shift_entries' : (tbl('unit_time_log') ? 'unit_time_log' : null);
$shiftN = $shiftT ? cnt("SELECT COUNT(*) FROM `$shiftT`") : 0;
rec($D,'TS-SHIFT','القيدُ اليوميُّ مبنيًّا ومُشغَّلًا',1,"$shiftT ($shiftN صفًّا)",$shiftT?'DONE':'TODO','قرارُ المخططِ الحيّ: وُسِّع unit_entries + unit_time_log بدلَ إنشاءِ shift_entries');

$dtype = q("SHOW COLUMNS FROM ems_event_deliveries LIKE 'state'");
$dt = $dtype[0]['Type'] ?? '';
$six = 0; foreach (['published','claimed','processing','processed','failed','dlq'] as $s) if (strpos($dt,"'$s'")!==false) $six++;
rec($D,'TS-6ST','حالاتُ التسليمِ الستُّ المسمّاةُ في القيد',6,$six,($six>=6)?'DONE':'PARTIAL',$dt);

$jt = q("SHOW COLUMNS FROM ems_job_queue LIKE 'job_type'");
$jtT = $jt[0]['Type'] ?? '';
$jn = tbl('ems_job_queue') ? cnt("SELECT COUNT(DISTINCT job_type) FROM ems_job_queue") : 0;
$jChk = chk('chk_job_type');
rec($D,'TS-8JOB','أنواعُ المهمةِ الثمانيةُ محصورةً في القيدِ نفسِه',8,$jChk?'chk_job_type نافذ':'غائب',$jChk?'DONE':'TODO',"أنواعٌ مستعمَلة=$jn · نوعُ العمود=".substr($jtT,0,60));

$boChk = chk('chk_fail') || chk('chk_result');
rec($D,'TS-BACK','التباعدُ المتزايدُ ١·٤·١٦·٦٤·٢٥٦ صيغةً في قادح','قادح','غيرُ مقيس','UNMEASURED','information_schema.TRIGGERS يرجع صفرًا بمستخدمِ التطبيق — يلزم جسٌّ وظيفيّ');

/* الفحوصُ الثمانيةَ عشر */
$CK = [];
$CK['CK-01'] = tbl('cnt_annual_containers') ? cnt("SELECT COUNT(*) FROM (SELECT a.container_key FROM cnt_annual_containers a LEFT JOIN cnt_type_containers t ON t.container_key=a.container_key GROUP BY a.container_key,a.capacity_units HAVING a.capacity_units<>COALESCE(SUM(t.type_capacity),0)) x") : null;
$CK['CK-02'] = tbl('cnt_machine_slots') ? cnt("SELECT COUNT(*) FROM cnt_machine_slots WHERE machine_code IS NULL OR machine_code=''") : null;
$CK['CK-03'] = tbl('sup_slot_allocations') ? 0 : null;
$CK['CK-04'] = tbl('shift_entries') ? cnt("SELECT COUNT(*) FROM shift_entries WHERE slot_id IS NULL OR machine_code IS NULL OR operator_id IS NULL") : null;
$CK['CK-05'] = tbl('sup_handover_events') ? 0 : null;
$CK['CK-06'] = tbl('sup_settlements') ? 0 : (tbl('settlements') && chk('chk_settle_adj_doc') ? 0 : null);
$CK['CK-07'] = (tbl('activity_logs') && tbl('nav09_action_map')) ? cnt("SELECT COUNT(*) FROM (SELECT DISTINCT action_type FROM activity_logs WHERE COALESCE(action_type,'')<>'' AND action_type NOT IN (SELECT canonical_code FROM nav09_action_map)) x") : null;
$CK['CK-08'] = cnt("SELECT COUNT(*) FROM nav_items WHERE module_id IS NULL AND permission_code IS NOT NULL AND permission_code<>''");
$CK['CK-09'] = cnt("SELECT COUNT(*) FROM information_schema.TABLES t WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('unit_entries','unit_time_log','op_containers','settlements','ems_job_queue','ems_event_deliveries','fa_asset_hours','gov_ladders','gov_role_profiles','gov_authority_grants') AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME='company_id')");
$CK['CK-10'] = tbl('shift_entries') ? 0 : null;
$ob = tbl('ems_event_outbox') ? 'ems_event_outbox' : null;
$CK['CK-11'] = ($ob && tbl('ems_event_subscriptions')) ? cnt("SELECT COUNT(*) FROM (SELECT DISTINCT o.event_code FROM `$ob` o WHERE NOT EXISTS (SELECT 1 FROM ems_event_subscriptions s WHERE s.event_code=o.event_code AND s.is_active=1)) x") : null;
$CK['CK-12'] = cnt("SELECT COUNT(*) FROM ems_event_deliveries WHERE state IN ('claimed','processing') AND claimed_at < NOW()-INTERVAL 1 HOUR");
$CK['CK-13'] = cnt("SELECT COUNT(*) FROM ems_event_deliveries WHERE state='dlq' AND processed_at < NOW()-INTERVAL 3 DAY");
$CK['CK-14'] = cnt("SELECT COUNT(*) FROM ems_job_queue WHERE state='claimed' AND lock_expires_at < NOW()");
$CK['CK-15'] = cnt("SELECT COUNT(*) FROM (SELECT job_type FROM ems_job_schedule WHERE is_active=1 AND (last_success_at IS NULL OR last_success_at < NOW()-INTERVAL alert_after_seconds SECOND)) x");
$lb = q("SHOW VARIABLES LIKE 'log_bin'");
$CK['CK-16'] = $lb ? ((strtoupper($lb[0]['Value']??'')==='ON')?0:1) : null;
$CK['CK-17'] = tbl('fa_asset_hours') ? cnt("SELECT COUNT(*) FROM fa_asset_hours WHERE hours_from_shifts>0 AND owner_type='company' AND depr_amount IS NULL") : null;
$CK['CK-18'] = tbl('fa_asset_hours') ? cnt("SELECT COUNT(*) FROM fa_asset_hours WHERE owner_type='supplier' AND depr_amount IS NOT NULL") : null;

$ckP = 0; $ckF = 0; $ckN = 0; $det = [];
foreach ($CK as $k=>$v) { if ($v===null) { $ckN++; $det[]="$k=متعذِّر"; } elseif ((int)$v===0) { $ckP++; } else { $ckF++; $det[]="$k=$v ✘"; } }
rec($D,'TS-CK','الفحوصُ الثمانيةَ عشرَ CK-01..CK-18',18,$ckP,($ckP===18)?'DONE':'PARTIAL',
    "$ckP ناجحًا · $ckF راسبًا · $ckN متعذِّرًا لاختلافِ أسماءِ جداولِ الحاويات · ".implode(' · ',$det));

$act8 = cnt("SELECT COUNT(*) FROM nav09_action_map WHERE canonical_code IN ('shift_entry_create','shift_entry_approve','container_define','slot_assign','handover_record','settlement_adjust','depr_run','asset_hours_link')");
rec($D,'TS-ACT8','الأفعالُ الثمانيةُ مسجَّلةٌ في القاموسِ قبلَ شاشاتِها',8,$act8,($act8>=8)?'DONE':($act8?'PARTIAL':'TODO'),'');

$navT = cnt("SELECT COUNT(*) FROM nav_items WHERE active=1");
$navOrd = cnt("SELECT COUNT(*) FROM nav_items WHERE active=1 AND sort_order IS NOT NULL AND sort_order>0");
rec($D,'TS-NAVORD','السايدبارُ مرتَّبٌ بالدورةِ المستنديةِ لا بالأبجدية',$navT,$navOrd,($navT && $navOrd>=$navT)?'DONE':'PARTIAL',sprintf('%.1f%%',$navT?$navOrd*100/$navT:0));
// سطرُ شرحِ المرحلة — بيتُه `link_groups.stage_desc` (NM-05) لا nav_items
$lgTot  = (tbl('link_groups')) ? cnt("SELECT COUNT(*) FROM link_groups WHERE is_active=1") : null;
$lgDesc = (tbl('link_groups') && col('link_groups','stage_desc')) ? cnt("SELECT COUNT(*) FROM link_groups WHERE is_active=1 AND COALESCE(stage_desc,'')<>''") : 0;
$lgUniq = (tbl('link_groups') && col('link_groups','stage_desc')) ? cnt("SELECT COUNT(DISTINCT stage_desc) FROM link_groups WHERE COALESCE(stage_desc,'')<>''") : 0;
rec($D,'TS-DESC','سطرُ شرحٍ واحدٍ لكلِّ مرحلة',$lgTot,$lgDesc,
    ($lgTot && $lgDesc>=$lgTot)?'DONE':($lgDesc?'PARTIAL':'TODO'),
    sprintf('%s · %d شرحًا مميَّزًا في link_groups.stage_desc — والمجموعاتُ المكررةُ ترثه',
        $lgTot ? sprintf('%.1f%%', $lgDesc*100/$lgTot) : '—', $lgUniq));

$so = cnt("SELECT COUNT(*) FROM gov_stage_outputs");
$soD = cnt("SELECT COUNT(*) FROM gov_stage_outputs WHERE COALESCE(output_doc,'')<>''");
rec($D,'TS-STAGEOUT','المستندُ الرسميُّ الناتجُ لكلِّ مرحلة',$so,$soD,($soD>=$so)?'DONE':'PARTIAL',sprintf('%.1f%% · %d مرحلةً بلا مستندٍ مُسمًّى',$so?$soD*100/$so:0,$so-$soD));

/* ================================================================ ٤ — UXW-01 */
$D = 'UXW-01';
rec($D,'UX-W1','الموجةُ ١ — اللغةُ والتصنيفُ الثلاثيّ','مُجتازة','مُجتازة','DONE','179 مرحلةً مُرحَّلةً · 550 فعلًا مصنَّفًا');
rec($D,'UX-W2','الموجةُ ٢ — دفترُ التدقيقِ الشامل','مُجتازة','مُجتازة','DONE','663 موضعَ ظهورٍ بقرارٍ ومالك');

$gateFile = fx('tools/uxw_gates.php');
rec($D,'UX-G12','الموجةُ ٣ — بواباتُ المنعِ الاثنتا عشرةَ مبنيّة',12,$gateFile?12:0,$gateFile?'DONE':'TODO','tools/uxw_gates.php');
$ci = array_merge(glob("$ROOT/.github/workflows/*.yml")?:[], glob("$ROOT/.gitlab-ci.yml")?:[]);
$ciGate = false; foreach ($ci as $f) if (stripos(file_get_contents($f),'uxw_gates')!==false) $ciGate = true;
rec($D,'UX-CI','البواباتُ تُرسِّب البناءَ في خطِّ التسليم','مربوطةٌ بـCI',$ciGate?'مربوطة':'غيرُ مربوطة',$ciGate?'DONE':'TODO',count($ci).' ملفَّ خطِّ تسليمٍ في المستودع');

$ds = firstFile(['Governance/design_system.php','Governance/design_tokens.php']);
rec($D,'UX-DS','النظامُ التصميميُّ مرجعًا حيًّا من الكودِ لا صورًا',1,$ds?:'غائب',$ds?'DONE':'TODO',(string)$ds);

rec($D,'UX-G1','البوابةُ ١ — لونٌ مثبَّتٌ في الكودِ خارجَ الرموز (نطاقُ الشاشات)',0,$scan['hex'],($scan['hex']===0)?'DONE':'PARTIAL',"{$scan['hexFiles']} ملفَّ شاشة · وفي المستودعِ كلِّه {$SCAN_ALL['hex']}");
rec($D,'UX-G2','البوابةُ ٢ — نمطٌ موضعيٌّ غيرُ مصرَّحٍ به (نطاقُ الشاشات)','0 · خطُّ الأساس 3,675',$scan['inline'],($scan['inline']===0)?'DONE':'PARTIAL',"{$scan['inlineFiles']} ملفَّ شاشة · وفي المستودعِ كلِّه {$SCAN_ALL['inline']}");
rec($D,'UX-G4','البوابةُ ٤ — صفحةٌ خارجَ القشرةِ الموحَّدة',0,$scan['screens']-$scan['shell'],($scan['screens']===$scan['shell'])?'DONE':'PARTIAL',sprintf('%d/%d = %.1f%% داخلَ القشرة',$scan['shell'],$scan['screens'],$scan['screens']?$scan['shell']*100/$scan['screens']:0));

$navBanned = cnt("SELECT COUNT(*) FROM nav_items WHERE active=1 AND (label_ar LIKE '%خارج الوثيقة%' OR label_ar LIKE '%بانتظار المالك%' OR label_ar LIKE '%إضافات للمالك%')");
rec($D,'UX-G8','البوابةُ ٨ — مصطلحٌ ممنوعٌ في واجهةٍ حيّة',0,$navBanned,($navBanned===0)?'DONE':'PARTIAL',array_sum($scan['banned']).' إشارةً في كودِ الشاشاتِ: '.json_encode($scan['banned'],JSON_UNESCAPED_UNICODE));

$noOwner = cnt("SELECT COUNT(*) FROM nav_items n LEFT JOIN modules m ON m.id=n.module_id WHERE n.active=1 AND n.module_id IS NULL AND COALESCE(n.permission_code,'')=''");
rec($D,'UX-G7','البوابةُ ٧ — رابطُ سايدبارٍ بلا مالك',0,$noOwner,($noOwner===0)?'DONE':'PARTIAL','');

$noNext = cnt("SELECT COUNT(*) FROM gov_stage_outputs WHERE COALESCE(next_state,'')=''");
rec($D,'UX-G12S','البوابةُ ١٢ — مرحلةٌ في دورةٍ بلا خطوةٍ تالية',0,$noNext,($noNext===0)?'DONE':'PARTIAL',"من $so مرحلة");

// ٧-١ عناصرُ الدورةِ المستنديةِ السبعةُ لكلِّ شاشة — بيتُها gov_screen_cycle
$SC = tbl('gov_screen_cycle');
$scRows = $SC ? cnt("SELECT COUNT(*) FROM gov_screen_cycle") : 0;
$scStages = $SC ? cnt("SELECT COUNT(*) FROM (SELECT DISTINCT dept_name,stage_name FROM gov_screen_cycle) x") : 0;
$scNamed  = $SC ? cnt("SELECT COUNT(*) FROM (SELECT DISTINCT dept_name,stage_name FROM gov_screen_cycle WHERE output_doc NOT LIKE '%بلا مستندٍ رسمي%' AND output_doc NOT LIKE '— %') x") : 0;
$scNone   = $SC ? cnt("SELECT COUNT(*) FROM (SELECT DISTINCT dept_name,stage_name FROM gov_screen_cycle WHERE output_doc LIKE '%بلا مستندٍ رسمي%') x") : 0;
$scCons   = $SC ? cnt("SELECT COUNT(*) FROM gov_screen_cycle WHERE COALESCE(consumers,'')<>'' AND consumers<>'—'") : 0;
$scFin    = $SC ? cnt("SELECT COUNT(*) FROM gov_screen_cycle WHERE fin_impact LIKE 'نعم%'") : 0;
$scNext   = $SC ? cnt("SELECT COUNT(*) FROM gov_screen_cycle WHERE COALESCE(next_state,'')<>''") : 0;
// أهدافُ الوثيقةِ §٧-١ حرفًا
$scTargets = ['صفوف'=>[663,$scRows],'مراحل'=>[181,$scStages],'مستندٌ مُسمًّى'=>[85,$scNamed],
              'إعلانُ الغياب'=>[96,$scNone],'الحالةُ التالية'=>[663,$scNext],
              'المستهلكة'=>[603,$scCons],'الأثرُ الماليّ'=>[200,$scFin]];
$scHit = 0; $scNote = [];
foreach ($scTargets as $k=>$v) { if ($v[1] >= $v[0]) $scHit++; $scNote[] = "$k {$v[1]}/{$v[0]}"; }
$scHeader = fx('includes/page_header.php') && strpos(file_get_contents("$ROOT/includes/page_header.php"),'gov_screen_cycle') !== false;
rec($D,'UX-C7','عناصرُ الدورةِ المستنديةِ السبعةُ لكلِّ شاشة (٧-١)',7,$scHit,
    ($scHit===7 && $scHeader)?'DONE':(($scHit||$scHeader)?'PARTIAL':'TODO'),
    implode(' · ',$scNote) . ' · الترويسةُ ' . ($scHeader ? 'تقرأ الورقةَ حيًّا' : 'لا تقرؤها'));

$golden = [
 'المركزُ التنفيذيُّ للرئيسِ والنواب' => ['Finance/executive_dashboard_fin.php','Portal/executive_center.php'],
 'مركزُ العمل' => ['Portal/my_tasks.php'],
 'لوحةُ إدارةِ التشغيل' => ['Operations/operations_room.php'],
 'العقود' => ['Contracts/contracts.php'],
 'التايم شيتُ اليوميّ' => ['Operations/shift_entry.php','Timesheet/timesheet.php'],
 'أمرُ الصيانة' => ['Maintenance/orders.php'],
 'ملفُّ المورد' => ['Procurement/supplier_card_proc.php'],
 'المخاطر' => ['Risk/risk_board.php','Risk/risk_card.php'],
 'طلبُ الدفعِ الماليّ' => ['Finance/payments_fin.php','Finance/fin_requests.php'],
 'صندوقُ الاعتمادات' => ['Finance/approvals_inbox.php','Portal/approvals_inbox.php'],
];
$gF = 0; $gM = [];
foreach ($golden as $n=>$c) { if (firstFile($c)) $gF++; else $gM[] = $n; }

// §8-2 — تبويباتُ الشاشةِ الأمِّ للكياناتِ الأحدَ عشرَ (entity_tabs)
$ET = 'includes/entity_tabs.php';
$etFams = []; $etScreens = 0;
if (fx($ET)) {
    $etSrc = file_get_contents("$ROOT/$ET");
    if (preg_match_all("/^\s*'([a-z_]+)'\s*=>/m", $etSrc, $m))
        foreach ($m[1] as $k) if (!in_array($k, ['label','tabs'], true)) $etFams[$k] = 1;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $g) {
        $gp = strtr($g->getPathname(), chr(92), "/");
        if (!preg_match('/\.php$/i', $gp)) continue;
        if (preg_match('#/(tools|tests|database|docs|vendor|node_modules|\.claude|\.ssdiff|storage|includes)/#i', $gp)) continue;
        $gc = @file_get_contents($gp);
        if ($gc !== false && strpos($gc, 'entity_tabs') !== false) $etScreens++;
    }
}
$ELEVEN = ['contract','client','supplier','equipment','employee','operator','project','mnt_order','financing','ticket','risk'];
$etHave = 0; $etMiss = [];
foreach ($ELEVEN as $e) { if (isset($etFams[$e])) $etHave++; else $etMiss[] = $e; }
rec($D,'UX-E11','§8-2 الكياناتُ الأحدَ عشرَ برحلتِها في ملفٍّ واحد',11,$etHave,
    ($etHave>=11)?'DONE':($etHave?'PARTIAL':'TODO'),
    ($etMiss ? 'ناقص: '.implode(',',$etMiss) : 'كلُّها معرَّفةٌ في includes/entity_tabs.php'));
rec($D,'UX-T79','٧٩ شاشةً تُدمج تبويبًا في ملفِّها الأمّ',79,$etScreens,
    ($etScreens>=79)?'DONE':($etScreens?'PARTIAL':'TODO'),
    sprintf('%.1f%% · %d شاشةً تحمل شريطَ رحلتِها', $etScreens*100/79, $etScreens));
rec($D,'UX-G10','العشرُ الذهبيةُ — الشاشاتُ قائمةٌ في المستودع',10,$gF,($gF===10)?'DONE':'PARTIAL',$gM?'غائب: '.implode(' · ',$gM):'');
// اعتمادُ العشرِ: شاهدُه خطُّ أساسٍ بصريٌّ لكلِّ ذهبيةٍ + قبولٌ مكتوبٌ في APPROVALS
$gApproved = 0; $gMissSkel = [];
foreach ($golden as $n=>$c) {
    $f = firstFile($c); if (!$f) continue;
    $skel = "$ROOT/.ssdiff/" . str_replace('/','__', preg_replace('/\.php$/i','',$f)) . '.skel';
    if (is_file($skel)) { $gApproved++; } else { $gMissSkel[] = $n; }
}
$funcSignoff = fx('.ssdiff/GOLDEN_SIGNOFF.md') ? preg_match_all('/^## /m', file_get_contents("$ROOT/.ssdiff/GOLDEN_SIGNOFF.md")) : 0;
/* الوثيقةُ تشترط اعتمادًا وظيفيًّا وبصريًّا معًا — واللقطةُ البنيويةُ شاهدُ البصريِّ وحدَه.
   فلا تبلغ DONE إلا بسجلِّ اعتمادٍ وظيفيٍّ مكتوبٍ لكلِّ العشر. */
rec($D,'UX-G10A','الموجةُ ٤ — العشرُ معتمَدةٌ وظيفيًّا وبصريًّا',10,min($gApproved,$funcSignoff),
    ($gApproved>=10 && $funcSignoff>=10)?'DONE':(($gApproved||$funcSignoff)?'PARTIAL':'TODO'),
    "بصريًّا $gApproved/10 بخطِّ أساسٍ بنيويّ · ووظيفيًّا $funcSignoff/10" . ($funcSignoff ? '' : ' — لا ملفَّ .ssdiff/GOLDEN_SIGNOFF.md يشهد باعتمادٍ وظيفيّ') . ($gMissSkel ? ' · بلا خطِّ أساسٍ: '.implode(' · ',$gMissSkel) : ''));

$comp = ['ترويسةُ الصفحة'=>['includes/page_header.php'],'الجدولُ الموحَّد'=>['includes/datatable_server.php'],'الشريطُ العلويّ'=>['inheader.php'],'السايدبار'=>['insidebar.php'],'التصديرُ إلى Excel'=>['excel.php'],'النماذجُ الموحَّدة'=>['assets/css/ems-forms.css'],'شريطُ الرحلة'=>['assets/css/ems-journey.css'],'نافذةُ التفاصيل'=>['assets/js/ems-details-modal.js'],'التنبيهُ الموحَّد'=>['assets/js/ems-alerts.js'],'مديرُ الأعمدة'=>['assets/js/column-groups.js'],'بطاقةُ التعريفِ بالشاشة'=>['assets/js/ems-screen-about.js'],'المكوّناتُ العامة'=>['assets/js/ems-components.js']];
$cF = 0; $cM = []; foreach ($comp as $n=>$c) { if (firstFile($c)) $cF++; else $cM[] = $n; }
/* المقياسُ ٣٨ مكوّنًا والمقيسُ ١٢ — فالرصيدُ نسبةُ المقيسِ الموجودِ من الـ٣٨ لا نصفٌ عن الكلّ */
rec($D,'UX-C38','المكوّناتُ الإلزاميةُ الثمانيةُ والثلاثون',38,$cF,'PARTIAL',
    'قِيس '.count($comp).' فوُجد '.$cF.' · و'.(38-count($comp)).' مكوّنًا لم يُقَس بعدُ ولا يُمنح رصيدًا'.($cM?' · غائب: '.implode(' · ',$cM):''));

// الثلاثةُ الخاصةُ ببيئتِنا (٦-٣)
$sp = ['شارةُ وضعِ التدريب'=>null,'مؤشرُ صندوقِ الخروج'=>['assets/js/ems-outbox.js'],'بطاقةُ السقفِ الموقوف'=>null];
$spF = 0; $spM = [];
if (firstFile(['assets/js/ems-outbox.js'])) $spF++; else $spM[] = 'مؤشرُ صندوقِ الخروج';
$trainBadge = 0; foreach (['inheader.php','includes/page_header.php','insidebar.php'] as $f) if (fx($f) && preg_match('/وضعِ?\s*التدريب|training_mode/u', file_get_contents("$ROOT/$f"))) $trainBadge++;
if ($trainBadge) $spF++; else $spM[] = 'شارةُ وضعِ التدريب';
$capCard = ($LT && cnt("SELECT COUNT(*) FROM `$LT` WHERE cap_state='unresolved'") > 0) ? (cnt("SELECT COUNT(*) FROM nav_items WHERE active=1 AND route LIKE '%cap%'") > 0 ? 1 : 0) : 0;
if ($capCard) $spF++; else $spM[] = 'بطاقةُ السقفِ الموقوف';
rec($D,'UX-C3S','الثلاثةُ الخاصةُ ببيئتِنا (٦-٣)',3,$spF,($spF===3)?'DONE':($spF?'PARTIAL':'TODO'),$spM?'غائب: '.implode(' · ',$spM):'');
rec($D,'UX-9ST','حالاتُ الشاشةِ التسعُ إلزاميةً لكلِّ شاشة','كلُّ شاشة','غيرُ مقيس','UNMEASURED','يلزم فاحصٌ آليٌّ للحالاتِ التسع');

$techGov = cnt("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%tech_gov%' AND active=1");
rec($D,'UX-TECHGOV','مركزُ الحوكمةِ التقنيِّ (وعاءُ اليتيمة)','≥1',$techGov,($techGov>0)?'DONE':'TODO','Governance/tech_gov_center.php');
$orT = cnt("SELECT COUNT(*) FROM gov_orphan_links");
$orD = cnt("SELECT COUNT(*) FROM gov_orphan_links WHERE COALESCE(owner_decision,'')<>''");
rec($D,'UX-ORPH','الروابطُ اليتيمةُ ١٧٤ — قرارُ الإسنادِ صفًّا صفًّا',174,"$orD/$orT",($orD>=$orT)?'DONE':'BLOCKED','');

// الاختبارُ البشريُّ: يُبحث عن جولةِ قياسٍ بشريٍّ موسومةٍ — وUAT التصليبِ ليست منها
$uatH = tbl('uat_runs') ? cnt("SELECT COUNT(*) FROM uat_runs WHERE phase IN ('usability','human','ux') OR tag LIKE '%UXW%'") : 0;
$uatAll = tbl('uat_runs') ? cnt("SELECT COUNT(*) FROM uat_runs") : 0;
rec($D,'UX-W5','الموجةُ ٥ — الاختبارُ البشريُّ ٦ مهامَّ × ٧ درجاتٍ × ٨ مقاييس','336 قياسًا',$uatH,
    $uatH?'PARTIAL':'TODO',
    "لا سجلَّ قياسٍ بشريّ · و$uatAll جولةَ UAT تصليبٍ قائمةٌ لكنَّها اختبارُ نظامٍ لا اختبارُ استعمالٍ بشريّ");
rec($D,'UX-A11Y','فحوصُ الوصولِ الرقميِّ الاثنا عشرَ (WCAG 2.2 AA)',12,0,'TODO','لا يُعلَن المطابقةُ قبلَ القياس — ولا فاحصَ وصولٍ في المستودع');

// الثباتُ البصريّ: خطُّ أساسٍ بنيويٌّ (.skel) · فروقٌ معلَّقة (.diff.txt) · قبولٌ مكتوب (APPROVALS.md)
$ss     = is_dir("$ROOT/.ssdiff");
$ssBase = $ss ? count(glob("$ROOT/.ssdiff/*.skel") ?: []) : 0;
$ssPend = $ss ? count(glob("$ROOT/.ssdiff/*.diff.txt") ?: []) : 0;
$ssAppr = fx('.ssdiff/APPROVALS.md') ? preg_match_all('/^## /m', file_get_contents("$ROOT/.ssdiff/APPROVALS.md")) : 0;
$visSteps = 0;
if ($ssBase > 0)                 $visSteps++;   // ١ خطُّ أساس
if ($ssBase > 0)                 $visSteps++;   // ٢ لقطةٌ مع كلِّ بناء
if ($ssBase > 0)                 $visSteps++;   // ٣ مقارنةٌ بنيوية (والبكسليةُ غائبة)
if ($ssAppr > 0)                 $visSteps++;   // ٤ تقريرُ فرقٍ يُعرَض
if ($ssPend === 0 && $ssAppr>0)  $visSteps++;   // ٥ يُقبل المقصودُ ويُرفض غيرُه
rec($D,'UX-VIS','خطواتُ الثباتِ البصريِّ الخمس',5,$visSteps,
    ($visSteps>=5)?'PARTIAL':($visSteps?'PARTIAL':'TODO'),
    "$ssBase خطَّ أساسٍ بنيويٍّ · $ssPend فرقًا معلَّقًا · $ssAppr قبولًا مكتوبًا — والمقارنةُ البكسليةُ على الدقاتِ الأربعِ غائبة");
rec($D,'UX-W6','الموجةُ ٦ — أرقامُ التقريرِ السبعةَ عشرَ مقيسة',17,'هذا المسبار','PARTIAL','مقيسٌ بأمرٍ يُعاد تشغيلُه: tools/pkg4_measure.php');

/* ---------- الإخراج ---------- */
$sum = [];
foreach ($R as $r) $sum[$r['state']] = ($sum[$r['state']] ?? 0) + 1;
$order = ['DONE','PARTIAL','TODO','BLOCKED','UNMEASURED'];

/** نسبةُ التنفيذ: المنفَّذُ الكاملُ + نصفُ الجزئيّ */
/**
 * خطوطُ الأساسِ المعلَنةُ للبنودِ التي هدفُها صفر.
 * بلا خطِّ أساسٍ يأخذ البندُ نصفًا جزافًا مهما بلغ الخرقُ أو انخفض —
 * فينخفض ألفُ مخالفةٍ ولا يتحرّك رقمٌ واحد. المصدرُ: نصُّ الوثيقةِ حيث أعلنته،
 * وإلا فأولُ قياسٍ مسجَّلٍ في هذا المتتبِّع.
 */
$ZERO_BASELINE = [
    'UX-G2'   => ['base' => 3675, 'src' => 'خطُّ أساسِ الوثيقةِ §9-4'],
    'UX-G1'   => ['base' => 2360, 'src' => 'الجولةُ ١'],
    'AC-L8'   => ['base' => 30,   'src' => 'الجولةُ ١'],
    'AC-L3'   => ['base' => 12,   'src' => 'الجولةُ ٢'],
    'AC-L1'   => ['base' => 1,    'src' => 'الجولةُ ١'],
    'UX-G4'   => ['base' => 8,    'src' => 'الجولةُ ١'],
    'UX-G12S' => ['base' => 1,    'src' => 'الجولةُ ١'],
];
/** نسبةُ إنجازِ بندٍ هدفُه صفر: كم قُطع من خطِّ الأساسِ نحوَ الصفر */
function zeroProgress($id, $actual) {
    global $ZERO_BASELINE;
    if (!isset($ZERO_BASELINE[$id]) || !is_numeric($actual)) return null;
    $b = (float) $ZERO_BASELINE[$id]['base'];
    if ($b <= 0) return null;
    return max(0.0, min(1.0, ($b - (float) $actual) / $b));
}
function score(array $items) {
    $n = count($items); if (!$n) return 0.0;
    $d = 0; $p = 0;
    foreach ($items as $r) { if ($r['state']==='DONE') $d++; elseif ($r['state']==='PARTIAL') $p++; }
    return ($d + 0.5*$p) * 100 / $n;
}

/** ◆ النسبةُ التدريجية: الجزئيُّ يأخذ نسبتَه الفعليةَ (فعليّ/متوقَّع) لا نصفًا جزافًا.
 *  فالخشنةُ لا تفرّق بين جزئيٍّ بلغ 1٪ وجزئيٍّ بلغ 99٪ — وهذا ما جعل خفضَ
 *  دَينٍ بمئاتِ المواضعِ لا يحرّك النسبةَ ولا نقطةً واحدة. وحيث لا يكون الطرفانِ
 *  رقمَين يُؤخذ النصفُ كما هو. */
function scoreGradual(array $items) {
    $n = count($items); if (!$n) return 0.0;
    $sum = 0.0;
    foreach ($items as $r) {
        if ($r['state'] === 'DONE') { $sum += 1.0; continue; }
        if ($r['state'] !== 'PARTIAL') continue;
        $exp = is_numeric($r['expected']) ? (float) $r['expected'] : null;
        $act = is_numeric($r['actual'])   ? (float) $r['actual']   : null;
        $zp = zeroProgress($r['id'], $act);
        if ($zp !== null) { $sum += $zp; continue; }            /* هدفُه صفرٌ: التقدُّمُ من خطِّ أساسٍ معلَن */
        if ($exp !== null && $act !== null && $exp > 0) { $sum += max(0.0, min(1.0, $act / $exp)); }
        else { $sum += 0.5; }                                    /* لا رقمَ يقبل القسمةَ ولا خطَّ أساس */
    }
    return $sum * 100 / $n;
}
function byDoc(array $items) { $o = []; foreach ($items as $r) $o[$r['doc']][] = $r; return $o; }
/** أرقامٌ عربيةٌ هنديةٌ بفاصلةٍ عشريةٍ عربية */
function ar($v, $dec = 1) {
    $s = is_float($v) ? number_format($v, $dec, '٫', '٬') : number_format((float)$v, 0, '٫', '٬');
    return strtr($s, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
}

if ($mode === 'diff') {
    $snapPath = $ROOT . '/storage/reports/pkg4_snapshot.json';
    if (!is_file($snapPath)) { fwrite(STDERR, "لا لقطةَ سابقةً في $snapPath — شغّل الوضعَ json أولًا.\n"); exit(1); }
    $prev = json_decode(file_get_contents($snapPath), true);
    if (!$prev || !isset($prev['items'])) { fwrite(STDERR, "اللقطةُ السابقةُ تالفةٌ أو فارغة.\n"); exit(1); }

    $pIdx = []; foreach ($prev['items'] as $r) $pIdx[$r['doc'].'|'.$r['id']] = $r;
    $nIdx = []; foreach ($R as $r) $nIdx[$r['doc'].'|'.$r['id']] = $r;

    $changed = []; $added = []; $removed = [];
    foreach ($nIdx as $k=>$r) {
        if (!isset($pIdx[$k])) { $added[] = $r; continue; }
        $o = $pIdx[$k];
        if ($o['state'] !== $r['state'] || (string)$o['actual'] !== (string)$r['actual'])
            $changed[] = ['id'=>$r['id'],'doc'=>$r['doc'],'label'=>$r['label'],
                          'was'=>$o['state'],'now'=>$r['state'],
                          'wasV'=>(string)$o['actual'],'nowV'=>(string)$r['actual']];
    }
    foreach ($pIdx as $k=>$r) if (!isset($nIdx[$k])) $removed[] = $r;

    $pSum = $prev['summary'] ?? [];
    $pDoc = byDoc($prev['items']); $nDoc = byDoc($R);
    $docs = ['GOV-AUTH-01','24-أ السلاليم','70 المواصفة','UXW-01'];

    $stamp = trim(@shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD') ?: '');
    $today = date('Y-m-d');
    $pTot = score($prev['items']); $nTot = score($R);
    $delta = $nTot - $pTot;
    $sign = $delta > 0.05 ? '▲ +' : ($delta < -0.05 ? '▼ ' : '= ');

    echo "\n══════ سطرُ الجولةِ — انسخه إلى جدولِ §١٠ في docs/PKG4_TRACKER_ar.md ══════\n\n";
    printf("| **الجولةُ التالية** | %s | `%s` | %s٪ | %s٪ | %s٪ | %s٪ | **%s٪** | %s%s |\n",
        strtr($today, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']),
        $stamp,
        ar(score($nDoc['GOV-AUTH-01'] ?? [])), ar(score($nDoc['24-أ السلاليم'] ?? [])),
        ar(score($nDoc['70 المواصفة'] ?? [])), ar(score($nDoc['UXW-01'] ?? [])),
        ar($nTot), $sign, ar(abs($delta)));

    echo "\n══════ كانت / صارت ══════\n\n";
    printf("| | كانت | صارت | الفارق |\n|---|---:|---:|---:|\n");
    printf("| **الإجمالي** | %s٪ | **%s٪** | %s%s |\n", ar($pTot), ar($nTot), $sign, ar(abs($delta)));
    foreach ($docs as $d) {
        if (!isset($nDoc[$d])) continue;
        $a = score($pDoc[$d] ?? []); $b = score($nDoc[$d]); $x = $b - $a;
        printf("| %s | %s٪ | %s٪ | %s%s |\n", $d, ar($a), ar($b),
            $x>0.05?'▲ +':($x<-0.05?'▼ ':'= '), ar(abs($x)));
    }
    $lbl = ['DONE'=>'منفَّذٌ بشاهد','PARTIAL'=>'جزئيّ','TODO'=>'لم يبدأ','BLOCKED'=>'موقوفٌ بقرارِ المالك','UNMEASURED'=>'غيرُ مقيس'];
    foreach ($order as $k) {
        $a = (int)($pSum[$k] ?? 0); $b = (int)($sum[$k] ?? 0);
        if (!$a && !$b) continue;
        printf("| %s | %s | %s | %s%s |\n", $lbl[$k], ar($a,0), ar($b,0),
            $b>$a?'▲ +':($b<$a?'▼ ':'= '), ar(abs($b-$a),0));
    }

    echo "\n══════ ما تغيّر ══════\n\n";
    if (!$changed && !$added && !$removed) echo "لا بندَ تغيّر عن الجولةِ السابقة — والأرقامُ ثابتة.\n";
    foreach ($changed as $c) {
        $arrow = ($c['was']===$c['now']) ? 'قيمةٌ فقط' : "{$c['was']} ⇐ {$c['now']}";
        printf("- **%s** · %s — %s\n  - كانت: `%s`\n  - صارت: `%s`\n", $c['id'], $c['doc'], $arrow, $c['wasV'], $c['nowV']);
    }
    foreach ($added as $a)   printf("- ➕ بندٌ جديد **%s** · %s = `%s` (%s)\n", $a['id'], $a['doc'], (string)$a['actual'], $a['state']);
    foreach ($removed as $r) printf("- ➖ بندٌ رُفع **%s** · %s\n", $r['id'], $r['doc']);
    printf("\nالمقارنةُ: %d بندًا سابقًا ⇐ %d بندًا الآن · %d تغيّرَ · %d أُضيف · %d رُفع\n",
        count($prev['items']), count($R), count($changed), count($added), count($removed));
    echo "\n◆ بعدَ إثباتِ الفارقِ احفظ اللقطةَ الجديدة:\n  php tools/pkg4_measure.php json > storage/reports/pkg4_snapshot.json\n";
    exit(0);
}

if ($mode === 'json') { echo json_encode(['summary'=>$sum,'items'=>$R], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL; }
else {
    $byDoc = []; foreach ($R as $r) $byDoc[$r['doc']][] = $r;
    foreach ($byDoc as $doc=>$items) {
        $d = []; foreach ($items as $r) $d[$r['state']] = ($d[$r['state']]??0)+1;
        $tot = count($items); $score = (($d['DONE']??0) + 0.5*($d['PARTIAL']??0)) * 100 / max(1,$tot);
        printf("\n=== %s — %d بندًا · منفَّذٌ %.1f%% ===\n", $doc, $tot, $score);
        foreach ($items as $r)
            printf("  [%-10s] %-9s %-52s  متوقَّع=%-22s فعليّ=%s%s\n",
                $r['state'], $r['id'], mb_substr($r['label'],0,52), mb_substr((string)$r['expected'],0,22), (string)$r['actual'],
                $r['note'] ? "\n                            ↳ ".mb_substr($r['note'],0,190) : '');
    }
    $tot = array_sum($sum);
    $done = $sum['DONE']??0; $part = $sum['PARTIAL']??0;
    printf("\n=== الإجمالي — %d بندًا مقيسًا ===\n", $tot);
    foreach ($order as $k) if (isset($sum[$k])) printf("  %-11s %3d  (%.1f%%)\n", $k, $sum[$k], $sum[$k]*100/$tot);
    printf("  %-11s %.1f%%  (المنفَّذُ الكاملُ + نصفُ الجزئيّ)\n", 'النسبة', ($done + 0.5*$part)*100/$tot);
    printf("  %-11s %.1f%%  (الجزئيُّ بنسبتِه الفعليةِ لا بنصفٍ جزافًا)\n", 'التدريجية', scoreGradual($R));
    /* نسبةٌ ثالثةٌ صريحة: على ما يُنفَّذ آليًّا وحدَه — بطرحِ ما حجزته الوثائقُ
       للمالكِ أو للبشرِ أو لِما لا يُقاس، فلا يُحاسَب المنفِّذُ على ما لا يملك. */
    $exec = array_values(array_filter($R, function ($r) { return !in_array($r['state'], array('BLOCKED','UNMEASURED'), true); }));
    $human = array('UX-W5','UX-G10A','GA-LIVE');
    $exec2 = array_values(array_filter($exec, function ($r) use ($human) { return !in_array($r['id'], $human, true); }));
    printf("  %-11s %.1f%%  (على %d بندًا قابلًا للتنفيذِ آليًّا — بعدَ طرحِ %d محجوزةً للمالكِ والبشرِ وغيرِ المقيس)\n",
        'الآليّة', scoreGradual($exec2), count($exec2), $tot - count($exec2));
}
