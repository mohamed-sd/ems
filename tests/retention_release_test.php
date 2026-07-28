<?php
/**
 * tests/retention_release_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * **ردُّ ضمان حسن التنفيذ** — CON-02 ق-20 (البندُ الأخيرُ في دورة الاستقطاع).
 *
 *   ① الردُّ **يُرفض قبل تجاوز `actual_end`** — «بعد انتهاء العقد» شرطٌ لا شعار
 *   ② والردُّ **يُرفض من غير الدور 19** — ق-20 · ق-13
 *   ③ والردُّ ينتج **بندًا موجبًا يساوي الرصيدَ المحتجزَ بالضبط** — كاملًا بلا
 *      خصمِ الغرامات المعلّقة (خُصمت في مستخلصاتها — منعُ الازدواج)
 *   ④ و**لا يُردُّ ضمانٌ مرتين** (عطالة)
 *   ⑤ والرصيدُ بعده **صفر**
 *   ⑥ ⚠️ **وصفرُ إيرادٍ جديدٍ في الدفتر**: الاحتجازُ لم يدخل الدفترَ أصلًا
 *      (بندُ `retention` بـ`event_id = NULL`)، فردُّه لا يخلق إيرادًا. ولو نُشر
 *      قيدَ `revenue` لتضخّم إيرادُ العقد بمقدار المحتجَز — وهو من صنف ازدواج
 *      56,000/28,000. هذا التأكيدُ هو **حارسُ ذلك الازدواج**.
 *
 * يبذر عالَمَه المستقلَّ ويكنسه.
 * التشغيل: php tests/retention_release_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '19', 'company_id' => 4, 'name' => 'retention test');

require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$conn = $GLOBALS['conn'];
$gate = ems_tenant_db();
$CO = 4;
$TAG = 'RET' . getmypid();
$FINMGR = 74;    // مديرُ المالية — الدور 19
$SALES  = 13;    // المبيعات — الدور 12
$HELD   = 500.00;

$root = new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($root->connect_error) { fwrite(STDERR, "root: {$root->connect_error}\n"); exit(1); }
$root->set_charset('utf8mb4');

$cleanup = function () use ($root, $TAG) {
    // ⚠️ ADR-15: الإسقاطُ يُحذف **قبل** الجذر — لا العكس
    $root->query("DELETE cl FROM claim_lines cl JOIN claims c ON c.id=cl.claim_id
                  JOIN contracts ct ON ct.id=c.contract_id JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE c FROM claims c JOIN contracts ct ON ct.id=c.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE fel FROM fin_event_links fel JOIN fin_financial_events fe ON fe.id=fel.event_id
                  JOIN contracts ct ON ct.id=fe.contract_id JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE fe FROM fin_financial_events fe JOIN contracts ct ON ct.id=fe.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE be FROM ems_business_events be JOIN contracts ct ON ct.id=be.entity_id
                  JOIN project p ON p.id=ct.project_id
                  WHERE be.entity_type='contract' AND be.event_key='retention.released' AND p.name LIKE '$TAG%'");
    $root->query("DELETE ce FROM contractequipments ce JOIN contracts ct ON ct.id=ce.contract_id
                  JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE ct FROM contracts ct JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'");
    $root->query("DELETE FROM project WHERE name LIKE '$TAG%'");
    $root->query("DELETE FROM clients WHERE client_name LIKE '$TAG%'");
};
$cleanup();

function seed($root, $sql) {
    if (!$root->query($sql)) { throw new \RuntimeException('بذر: ' . $root->error . ' ← ' . substr($sql, 0, 160)); }
    return intval($root->insert_id);
}

fwrite(STDOUT, "══ CON-02 ق-20 — ردُّ ضمان حسن التنفيذ ══\n");

try {
head('① بذرُ عقدين: منتهٍ وسارٍ — والرصيدُ محتجَزٌ على المنتهي');
$ENDED   = '2026-03-31';                        // مضى
$RUNNING = date('Y-m-d', strtotime('+120 days'));  // لم يأتِ بعد

$CL  = seed($root, "INSERT INTO clients (company_id, client_name, phone, status) VALUES ($CO,'$TAG-عميل','0',1)");
$PRJ = seed($root, "INSERT INTO project (company_id, name, client, client_id, location, total, status)
                    VALUES ($CO,'$TAG-مشروع','$TAG-عميل',$CL,'اختبار','0',1)");
$CON = seed($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract,
                    actual_start, actual_end, daily_work_hours, retention_pct, advance_recovery_pct, status)
                    VALUES ($CO,$PRJ,'2026-01-01','دولار','2026-01-01','$ENDED','20',5.00,10.00,1)");
$CON2 = seed($root, "INSERT INTO contracts (company_id, project_id, contract_signing_date, price_currency_contract,
                     actual_start, actual_end, daily_work_hours, retention_pct, advance_recovery_pct, status)
                     VALUES ($CO,$PRJ,'2026-01-01','دولار','2026-01-01','$RUNNING','20',5.00,10.00,1)");

// مستخلصُ فترةٍ سابقٌ يحمل بندَ الاحتجاز السالب — **بلا `event_id`**، كما يولّده
// `claim_penalty_lines` تمامًا: الاحتجازُ خصمُ مطالبةٍ لا قيدٌ في الدفتر.
$CLM = seed($root, "INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
                    period_from, period_to, currency, gross_amount, net_amount, state)
                    VALUES ($CO,'CLM-$TAG-01',$CON,$CL,$PRJ,'2026-02-01','2026-02-28','USD',9500.00,9500.00,'approved')");
seed($root, "INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, event_id,
             work_date, unit_type, qty, unit_price, amount)
             VALUES ($CO,$CLM,'retention',$CON,NULL,'2026-02-28','retention',1,-$HELD,-$HELD)");
// وضمانٌ على العقد الساري كذلك — فشرطُ الزمن هو ما يمنع ردَّه لا خلوُّ الرصيد
$CLM2 = seed($root, "INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
                     period_from, period_to, currency, gross_amount, net_amount, state)
                     VALUES ($CO,'CLM-$TAG-02',$CON2,$CL,$PRJ,'2026-02-01','2026-02-28','USD',9500.00,9500.00,'approved')");
seed($root, "INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, event_id,
             work_date, unit_type, qty, unit_price, amount)
             VALUES ($CO,$CLM2,'retention',$CON2,NULL,'2026-02-28','retention',1,-$HELD,-$HELD)");

$bal0 = claim_retention_balance($gate, $CON);
check(abs($bal0 - $HELD) < 0.005, "الرصيدُ المحتجَزُ قبل الردّ = " . number_format($bal0, 2) . " (متوقَّع " . number_format($HELD, 2) . ")");

// خطُّ الأساس المالي: كم قيدَ إيرادٍ على العقد **قبل** الردّ؟
$revBefore = $root->query("SELECT COUNT(*) n, COALESCE(SUM(amount),0) s FROM fin_financial_events
                            WHERE company_id=$CO AND contract_id=$CON AND event_type='revenue'
                              AND COALESCE(is_deleted,0)=0")->fetch_assoc();
info('قيودُ الإيراد قبل الردّ: ' . intval($revBefore['n']) . ' بمجموع ' . number_format((float) $revBefore['s'], 2));

head('② الردُّ يُرفض قبل تجاوز actual_end (ق-20)');
$r = claim_retention_release($conn, $CON2, $FINMGR, '19');
check($r['status'] !== 'released', 'العقدُ السارِي لا يُردُّ ضمانُه: ' . $r['reason']);
check($r['code'] === 422, 'ورمزُ الرفض 422 (شرطٌ غيرُ مستوفٍ لا منعُ صلاحية) — جاء ' . $r['code']);
check(strpos($r['reason'], $RUNNING) !== false, 'والرسالةُ تقول **متى** يجوز: تحمل تاريخَ الانتهاء ' . $RUNNING);
$stillHeld = claim_retention_balance($gate, $CON2);
check(abs($stillHeld - $HELD) < 0.005, 'ورصيدُ العقد السارِي لم يُمسّ (' . number_format($stillHeld, 2) . ')');

head('③ الردُّ يُرفض من غير الدور 19 (ق-20 · ق-13)');
foreach (array('12' => 'المبيعات', '1' => 'التشغيل', '17' => 'محاسبٌ مالي') as $role => $who) {
    $r = claim_retention_release($conn, $CON, $SALES, $role);
    check($r['status'] !== 'released' && $r['code'] === 403,
          "دورُ {$who} ({$role}) لا يردّ الضمانَ — 403");
}
$bal1 = claim_retention_balance($gate, $CON);
check(abs($bal1 - $HELD) < 0.005, 'والرصيدُ بعد المحاولات المرفوضة كما كان (' . number_format($bal1, 2) . ')');

head('④ الردُّ بيد 19: بندٌ موجبٌ يساوي الرصيدَ بالضبط');
$rel = claim_retention_release($conn, $CON, $FINMGR, '19');
check($rel['status'] === 'released', 'رُدَّ الضمان: ' . $rel['reason']);
check($rel['code'] === 200, 'برمز 200 — جاء ' . $rel['code']);
check(abs((float) $rel['amount'] - $HELD) < 0.005,
      '**والمبلغُ الرصيدُ كاملًا** ' . number_format((float) $rel['amount'], 2)
      . ' — بلا خصمِ غراماتٍ معلّقة (خُصمت في مستخلصاتها)');

$newClaim = intval($rel['claim_id']);
check($newClaim > 0 && $newClaim !== $CLM, 'وأُنشئ **مستخلصٌ ختاميٌّ جديد** #' . $newClaim . ' لا بندًا في مستخلصٍ قائم');

$c = $root->query("SELECT period_from, period_to, state, gross_amount, net_amount, currency, notes
                     FROM claims WHERE id=$newClaim")->fetch_assoc();
check($c['period_from'] === $ENDED && $c['period_to'] === $ENDED,
      'وفترتُه يومُ انتهاء العقد (' . $c['period_from'] . ' → ' . $c['period_to'] . ') — الردُّ حدثٌ بتاريخه');
check($c['state'] === 'draft', 'وحالتُه مسودة — يُرفع ثم تُجيزه يدٌ ثانية، فلا مالَ بيدٍ واحدة');
check(abs((float) $c['net_amount'] - $HELD) < 0.005, 'وصافيه ' . number_format((float) $c['net_amount'], 2));

$ln = $root->query("SELECT source_kind, source_ref, event_id, amount, work_date
                      FROM claim_lines WHERE claim_id=$newClaim")->fetch_all(MYSQLI_ASSOC);
check(count($ln) === 1, 'وبندُه واحد — لا استقطاعَ يُعاد اقتطاعُه من الردّ (' . count($ln) . ')');
check($ln && $ln[0]['source_kind'] === 'retention_release', "ونوعُه `retention_release` (varchar(24) — صفرُ هجرة)");
check($ln && (float) $ln[0]['amount'] > 0 && abs((float) $ln[0]['amount'] - $HELD) < 0.005,
      '**وبندٌ موجبٌ** ' . ($ln ? number_format((float) $ln[0]['amount'], 2) : '؟') . ' يساوي المحتجَزَ بالضبط');
check($ln && $ln[0]['event_id'] === null,
      '**و`event_id = NULL`** كنظيرِه السالب — الردُّ تحريرُ مطالبةٍ لا اعترافٌ جديد');

head('⑤ الرصيدُ بعده صفر');
$bal2 = claim_retention_balance($gate, $CON);
check(abs($bal2) < 0.005, 'الرصيدُ التراكميُّ بعد الردّ = ' . number_format($bal2, 2) . ' (متوقَّع 0.00)');

head('⑥ ⚠️ صفرُ إيرادٍ جديدٍ في الدفتر — حارسُ الازدواج');
$revAfter = $root->query("SELECT COUNT(*) n, COALESCE(SUM(amount),0) s FROM fin_financial_events
                           WHERE company_id=$CO AND contract_id=$CON AND event_type='revenue'
                             AND COALESCE(is_deleted,0)=0")->fetch_assoc();
check(intval($revAfter['n']) === intval($revBefore['n']),
      '**عددُ قيود الإيراد لم يتغيّر** (' . intval($revBefore['n']) . ' → ' . intval($revAfter['n']) . ')');
check(abs((float) $revAfter['s'] - (float) $revBefore['s']) < 0.005,
      '**ومجموعُ الإيراد المعترَفِ به لم يتحرّك** ('
      . number_format((float) $revBefore['s'], 2) . ' → ' . number_format((float) $revAfter['s'], 2)
      . ') — الاحتجازُ لم يدخل الدفترَ فردُّه لا يضخّمه');

$fe = $root->query("SELECT COUNT(*) n FROM fin_financial_events
                     WHERE company_id=$CO AND contract_id=$CON AND source_ref='RET-$CON'")->fetch_assoc();
check(intval($fe['n']) === 0, 'وصفرُ قيدٍ ماليٍّ باسم الردّ في `fin_financial_events` — لا إسقاطَ في الدفتر');

$be = $root->query("SELECT id, event_key, amount, idempotency_key, entity_type, entity_id, payload
                      FROM ems_business_events
                     WHERE company_id=$CO AND event_key='retention.released'
                       AND entity_type='contract' AND entity_id=$CON")->fetch_assoc();
check($be !== null, '**لكنَّ الحقيقةَ مدوَّنةٌ في الجذر المحايد** `retention.released` — نمطُ claim_approve');
check($be && $be['idempotency_key'] === 'retention:release:contract:' . $CON,
      'وبمفتاح عطالةٍ صريح: ' . ($be ? $be['idempotency_key'] : '—'));
check($be && abs((float) $be['amount'] - $HELD) < 0.005,
      'وبمبلغ الردّ ' . ($be ? number_format((float) $be['amount'], 2) : '؟'));
$pl = $be ? json_decode($be['payload'], true) : array();
check(isset($pl['recognition']) && $pl['recognition'] === 'none',
      'ويعلن تصنيفَه صراحةً `recognition=none` — لا اعترافَ جديدًا');

head('⑦ ولا يُردُّ ضمانٌ مرتين (عطالة)');
$again = claim_retention_release($conn, $CON, $FINMGR, '19');
check($again['status'] === 'exists' && $again['code'] === 409,
      'المحاولةُ الثانيةُ ترتدّ بـ409: ' . $again['reason']);
check(intval($again['claim_id']) === $newClaim, 'وتدلّ على المستخلص القائم #' . intval($again['claim_id']));
$cnt = $root->query("SELECT COUNT(*) n FROM claim_lines cl JOIN claims c ON c.id=cl.claim_id
                      WHERE c.contract_id=$CON AND cl.source_kind='retention_release'")->fetch_assoc();
check(intval($cnt['n']) === 1, 'وبندُ الردّ واحدٌ لا اثنان (' . intval($cnt['n']) . ')');
$bal3 = claim_retention_balance($gate, $CON);
check(abs($bal3) < 0.005, 'والرصيدُ ما زال صفرًا بعد المحاولة الثانية (' . number_format($bal3, 2) . ')');

} catch (\Throwable $t) {
    bad('استثناء: ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine());
}

head('⑧ الكنس');
$cleanup();
$left = $root->query("SELECT COUNT(*) n FROM claims c JOIN contracts ct ON ct.id=c.contract_id
                      JOIN project p ON p.id=ct.project_id WHERE p.name LIKE '$TAG%'")->fetch_assoc();
check(intval($left['n']) === 0, 'صفرُ بقايا');

fwrite(STDOUT, "\n" . str_repeat('═', 60) . "\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL > 0 ? 1 : 0);
