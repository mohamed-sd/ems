<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * E-07 — اختبار قبول إلزام مسؤول التوقف (المعيار §9-④: «صفرُ فترةِ توقفٍ بلا مسؤول»)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/resp_party_stop_test.php
 *
 * ما يُثبته:
 *   ① القياس بعد الردم: صفرُ صفِّ توقفٍ resp='none' في السجل القانوني كلِّه.
 *   ② الردمُ من المصفوفة لا التخمين: الصفّان التاريخيان يحملان حكمَ عقدهما
 *      (fuel→client · equipment_readiness→company للعقد 5) وأثرَ تدقيق N-02.
 *   ③ الحارس: توقفٌ بلا مسؤول يُرمى تحت enforce — والعملُ الفعليُّ وunlogged
 *      (سلةُ الترحيل) خارج العائلة عمدًا.
 *   ④ الوصل في موضعَي الإدراج كليهما (القاعدة: الوصلُ في موضعين وإلا زخرفة).
 *   ⑤ العلمُ الحي enforce بعد نافذةِ رصدٍ نظيفة (N-04 §2-④).
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Services\Unit\TimesheetEntryService as SVC;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

fwrite(STDOUT, "\n══ E-07 — لا فترةَ توقفٍ بلا مسؤول ══\n");

// ═══ ① القياس ═══
head('① السجلُّ القانوني بعد الردم');
$r = $conn->query("SELECT COUNT(*) c FROM unit_time_log
                   WHERE resp_party='none'
                     AND ops_state IN ('tech_breakdown','supplier_stop','operator_stop',
                                       'client_stop','fuel_logistics_stop','planned_stop','force_majeure')");
check(intval($r->fetch_assoc()['c']) === 0, 'صفرُ فترةِ توقفٍ بمسؤول none (كان 2 قبل الردم)');
$r = $conn->query("SELECT COUNT(*) c FROM unit_time_log WHERE ops_state='actual_work' AND resp_party='none'");
check(intval($r->fetch_assoc()['c']) > 0, 'العملُ الفعليُّ يبقى بلا مسؤولٍ بحق — ليس توقفًا');

// ═══ ② الردمُ من المصفوفة ═══
head('② الصفّان التاريخيان بحكم مصفوفة عقدهما (5)');
$x = $conn->query("SELECT resp_party, obligation_type FROM unit_time_log WHERE id=1925")->fetch_assoc();
check($x && $x['resp_party'] === 'client' && $x['obligation_type'] === 'fuel',
      'صف 1925: fuel_logistics_stop → حكمُ العقد 5 لبند fuel = client (لا تخمين)');
$x = $conn->query("SELECT resp_party, obligation_type FROM unit_time_log WHERE id=1926")->fetch_assoc();
check($x && $x['resp_party'] === 'company' && $x['obligation_type'] === 'equipment_readiness',
      'صف 1926: tech_breakdown → حكمُ equipment_readiness = company');
// حكمُ المصدر مطابقٌ فعلًا لمصفوفة العقد 5 (تحصينُ الاختبار من ردمٍ أعمى)
$m = $conn->query("SELECT obligation_type, obligor FROM contract_obligations
                   WHERE client_contract_id=5 AND obligation_type IN ('fuel','equipment_readiness')
                     AND COALESCE(is_deleted,0)=0")->fetch_all(MYSQLI_ASSOC);
$mm = array();
foreach ($m as $row) { $mm[$row['obligation_type']] = $row['obligor']; }
check(($mm['fuel'] ?? '') === 'client' && ($mm['equipment_readiness'] ?? '') === 'company',
      'حكمُ الردم هو حكمُ المصفوفة حرفيًّا');
$r = $conn->query("SELECT COUNT(*) c FROM activity_logs
                   WHERE screen_name='seeds/e07_stop_resp_backfill' AND action_type='backfill'");
check(intval($r->fetch_assoc()['c']) >= 2, 'الردمُ مسجَّلٌ بقيم قبل/بعد (N-02)');

// ═══ ③ الحارس ═══
head('③ assertStopResponsible تحت enforce');
$ref = new ReflectionMethod(SVC::class, 'assertStopResponsible');
$ref->setAccessible(true);
$threw = false;
try { $ref->invoke(null, 'tech_breakdown', 'none'); } catch (\InvalidArgumentException $e) { $threw = true; }
check($threw, 'توقفٌ فنيٌّ بلا مسؤول → يُرمى (422)');
$threw = false;
try { $ref->invoke(null, 'client_stop', ''); } catch (\InvalidArgumentException $e) { $threw = true; }
check($threw, 'توقفُ عميلٍ بمسؤولٍ فارغ → يُرمى');
$okPass = true;
try {
    $ref->invoke(null, 'tech_breakdown', 'company');
    $ref->invoke(null, 'planned_stop', 'planned');
    $ref->invoke(null, 'actual_work', 'none');
    $ref->invoke(null, 'standby', 'none');
    $ref->invoke(null, 'unlogged', 'none');
} catch (\Throwable $e) { $okPass = false; }
check($okPass, 'التوقفُ بمسؤوله يمرّ · والعملُ/الاستعداد/unlogged خارج العائلة');

// ═══ ④ الوصل في الموضعين ═══
head('④ الوصلُ في موضعَي الإدراج (الإنشاءُ والتحديث)');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php');
$calls = substr_count($src, 'self::assertStopResponsible(');
check($calls === 2, "نداءان اثنان — الإنشاءُ والتحديث ({$calls})");
check(strpos($src, "const STOP_STATES") !== false, 'عائلةُ التوقف السبعُ ثابتةٌ معلنة');

// ═══ ⑤ العلم ═══
head('⑤ العلمُ الحي');
check((string) ems_env('EMS_RESP_PARTY_STRICT', '') === 'enforce',
      'EMS_RESP_PARTY_STRICT=enforce — قُلب بعد الردم بصفر would-reject (N-04 §2-④)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
