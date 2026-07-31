<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-01 — اختبار قبول: نطاقُ العقد التشغيلي وتنظيفُ الهرم (PLAN-03 §2.1)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_sites_test.php
 *
 * ما يُثبته:
 *   ① **صفرُ عقدٍ بلا مشروع** — `project_id NOT NULL` **بنيويًّا** (كتابةُ NULL تُرفض).
 *   ② **التعبئةُ الرجعيةُ كاملة**: كلُّ عقدٍ له `site_id` صار له **نطاقٌ رئيسي**
 *      — و**صفرُ عقدٍ حيٍّ بلا نطاق**.
 *   ③ **والموقعُ مرةً واحدةً في العقد** ⇒ 409 بمرجع القائم.
 *   ④ **ورئيسٌ واحدٌ على الأكثر** — والتعيينُ الثاني **ينقل الرئاسة ذرّيًّا**،
 *      و`UNIQUE` يرفض رئيسين بكتابةٍ مباشرة.
 *   ⑤ **والنطاقُ داخل مدة عقده** ⇒ 422 بتاريخي العقد.
 *   ⑥ **والإقفالُ بسببٍ مكتوب** (422 بلا سبب · و`CHECK` يرفض الكتابةَ المباشرة).
 *   ⑦ **وVIEW `client_contracts` يقرأ ما يقرؤه `contracts`** — لا جدولَ ثانيًا.
 *   ⑧ **وصفرُ فرقٍ في الفوترة**: المستخلصاتُ كما هي قبلَ وبعد.
 *
 * البذرُ معزول: عقدٌ ومشروعٌ وموقعان بوسمٍ فريد — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P01 sites test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractSiteService.php';

use App\Services\Contract\ContractSiteService as CSS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999011;
$MARK  = 'P01T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE o FROM contract_operational_sites o
                    JOIN contracts c ON c.id = o.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM sites WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-01 — نطاقُ العقد التشغيلي وتنظيفُ الهرم ══\n");

// ═══ ⑧ خطُّ الأساس قبل أي شيء ═══
$before = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(net_amount),0),2) s
                          FROM claims WHERE COALESCE(is_deleted,0)=0")->fetch_assoc();

head('① **صفرُ عقدٍ بلا مشروع** — بنيويًّا');

// ⚠ `project` يوجب name/client/location/total — والقياسُ قبل البذر لا الافتراض
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "
"); }
$PRJ = intval($conn->insert_id);
$mkSite = function ($n) use ($conn, $CO, $PRJ, $MARK) {
    $conn->query("INSERT INTO sites (company_id, project_id, name, site_kind, status, is_default,
                  is_deleted, created_at, updated_at)
                  VALUES ({$CO}, {$PRJ}, 'موقعُ {$MARK}-{$n}', 'site', 1, 0, 0, NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$S1 = $mkSite('أ');
$S2 = $mkSite('ب');

$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status,
              project_id, created_at)
              VALUES ({$CO}, '2083-01-01', 365, '2083-02-01', '2083-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, NOW())");
if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }
$CID = intval($conn->insert_id);
check($PRJ > 0 && $S1 > 0 && $S2 > 0 && $CID > 0,
      "مشروعٌ #{$PRJ} · موقعان #{$S1} و#{$S2} · عقدٌ #{$CID}");

$noProj = $conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
                        first_party, contract_status, created_at)
                        VALUES ({$CO}, '2083-01-01', 30, 'بلا مشروع {$MARK}', 'نافذ', NOW())");
check($noProj === false,
      '★★ عقدٌ **بلا مشروع** ترفضه البنية — «صفرُ عقدٍ بلا مشروع» لا رجاءً');

$bad = $conn->query("UPDATE contracts SET project_id=NULL WHERE id={$CID}");
$q = $conn->query("SELECT project_id FROM contracts WHERE id={$CID}")->fetch_assoc();
check($q['project_id'] !== null, '★ وتفريغُ المشروع من عقدٍ قائمٍ **مرفوضٌ كذلك**');

// ═══ ② التعبئةُ الرجعية ═══
head('② **التعبئةُ الرجعيةُ كاملة** — وصفرُ عقدٍ حيٍّ بلا نطاق');

$live = $conn->query("SELECT COUNT(*) c FROM contracts
                       WHERE COALESCE(is_deleted,0)=0 AND site_id IS NOT NULL
                         AND first_party NOT LIKE '%{$MARK}%'")->fetch_assoc();
$withScope = $conn->query("SELECT COUNT(DISTINCT o.contract_id) c
                             FROM contract_operational_sites o
                             JOIN contracts c ON c.id = o.contract_id
                            WHERE o.is_primary=1 AND c.first_party NOT LIKE '%{$MARK}%'")->fetch_assoc();
check((int) $live['c'] === (int) $withScope['c'] && (int) $live['c'] > 0,
      '★★ كلُّ عقدٍ له `site_id` صار له **نطاقٌ رئيسي**: '
      . $live['c'] . ' عقدًا = ' . $withScope['c'] . ' نطاقًا رئيسيًّا');

$orphan = $conn->query("SELECT COUNT(*) c FROM contract_operational_sites o
                          LEFT JOIN sites s ON s.id = o.site_id WHERE s.id IS NULL")->fetch_assoc();
check((int) $orphan['c'] === 0, 'وصفرُ نطاقٍ بموقعٍ مكسور — المفتاحُ الخارجيُّ يحرس');

// ═══ ③ الموقعُ مرةً واحدة ═══
head('③ **والموقعُ مرةً واحدةً في العقد**');

$a = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S1, 'is_primary' => 1), $ACTOR);
check($a['ok'], 'أُضيف النطاقُ الأول (رئيسي) #' . intval($a['scope_id']));
$SC1 = (int) $a['scope_id'];

$dup = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S1), $ACTOR);
check(!$dup['ok'] && $dup['code'] === 409 && (int) $dup['scope_id'] === $SC1,
      '★★ وإضافةُ الموقع نفسِه ⇒ **409 بمرجع القائم**');

$a2 = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S2, 'scope_name' => 'نطاقُ ' . $MARK . ' الثاني'), $ACTOR);
check($a2['ok'], '★ وموقعٌ ثانٍ في العقد نفسِه **يُقبل** — وهو ما كان مستحيلًا بعمودٍ واحد');
$SC2 = (int) $a2['scope_id'];

// ═══ ④ رئيسٌ واحد ═══
head('④ **ورئيسٌ واحدٌ على الأكثر**');

$p = CSS::setPrimary($conn, $gate, $CO, $SC2, $ACTOR);
check($p['ok'], 'نُقلت الرئاسةُ إلى النطاق الثاني');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_operational_sites
                           WHERE contract_id={$CID} AND is_primary=1")->fetch_assoc()['c']);
check($n === 1, '★★ ورئيسٌ **واحدٌ** بعد النقل لا اثنان');
$q = $conn->query("SELECT is_primary FROM contract_operational_sites WHERE id={$SC1}")->fetch_assoc();
check((int) $q['is_primary'] === 0, 'والأولُ فقد رئاستَه في المعاملة نفسِها');

$badP = $conn->query("UPDATE contract_operational_sites SET is_primary=1 WHERE id={$SC1}");
$n2 = intval($conn->query("SELECT COUNT(*) c FROM contract_operational_sites
                            WHERE contract_id={$CID} AND is_primary=1")->fetch_assoc()['c']);
check($badP === false && $n2 === 1,
      '★★ ورفعُ رئيسٍ ثانٍ **بكتابةٍ مباشرة يرفضه `UNIQUE`** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ⑤ داخل مدة العقد ═══
head('⑤ **والنطاقُ داخل مدة عقده**');

$S3 = $mkSite('ج');
$early = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S3, 'start_date' => '2083-01-01'), $ACTOR);
check(!$early['ok'] && $early['code'] === 422 && mb_strpos($early['reason'], 'قبل بداية العقد') !== false,
      '★★ بدايةٌ **قبل بداية العقد** ⇒ **422** بتاريخه: ' . mb_substr($early['reason'], 0, 60));

$late = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S3, 'end_date' => '2084-06-01'), $ACTOR);
check(!$late['ok'] && $late['code'] === 422 && mb_strpos($late['reason'], 'بعد نهاية العقد') !== false,
      '★ ونهايةٌ **بعد نهاية العقد** ⇒ **422**');

$fit = CSS::add($conn, $gate, $CO, $CID, array('site_id' => $S3,
    'start_date' => '2083-03-01', 'end_date' => '2083-09-30'), $ACTOR);
check($fit['ok'], 'ونطاقٌ داخل المدة يُقبل');
$SC3 = (int) $fit['scope_id'];

// ═══ ⑥ الإقفالُ بسبب ═══
head('⑥ **والإقفالُ بسببٍ مكتوب**');

$c1 = CSS::close($conn, $gate, $CO, $SC3, '   ', $ACTOR);
check(!$c1['ok'] && $c1['code'] === 422 && mb_strpos($c1['reason'], 'سببُ الإقفال إلزامي') !== false,
      'إقفالٌ بلا سببٍ ⇒ **422**');

$badC = $conn->query("UPDATE contract_operational_sites SET state='closed', close_reason=NULL
                       WHERE id={$SC3}");
$q = $conn->query("SELECT state FROM contract_operational_sites WHERE id={$SC3}")->fetch_assoc();
check((string) $q['state'] !== 'closed',
      '★★ وإقفالٌ مكتوبٌ مباشرةً بلا سببٍ **يرفضه `CHECK`**');

$c2 = CSS::close($conn, $gate, $CO, $SC3, 'انتهى العملُ في هذا الموقع', $ACTOR);
check($c2['ok'], 'وبسببٍ مكتوبٍ يُقفل');
$q = $conn->query("SELECT state, close_reason FROM contract_operational_sites WHERE id={$SC3}")->fetch_assoc();
check((string) $q['state'] === 'closed' && (string) $q['close_reason'] !== '',
      'والسببُ محفوظٌ في الصفّ');

$p3 = CSS::setPrimary($conn, $gate, $CO, $SC3, $ACTOR);
check(!$p3['ok'] && $p3['code'] === 422, '★ ولا يُرأَّس نطاقٌ مقفل');

// ═══ ⑦ VIEW ═══
head('⑦ **وVIEW `client_contracts` يقرأ ما يقرؤه `contracts`**');

$a1 = intval($conn->query("SELECT COUNT(*) c FROM contracts")->fetch_assoc()['c']);
$a2n = intval($conn->query("SELECT COUNT(*) c FROM client_contracts")->fetch_assoc()['c']);
check($a1 === $a2n, '★★ العددُ نفسُه (' . $a1 . ') — **لا جدولَ ثانيًا ولا تسميةَ قسرية**');
$v = $conn->query("SELECT primary_site_id, primary_scope_name FROM client_contracts
                    WHERE id={$CID}")->fetch_assoc();
check((int) $v['primary_site_id'] === $S2,
      '★ وVIEW يُظهر **نطاقَ العقد الرئيسي** (' . $S2 . ') لا العمودَ الموروث');

// ═══ ⑧ صفرُ فرقٍ في الفوترة ═══
head('⑧ **وصفرُ فرقٍ في الفوترة**');
$after = $conn->query("SELECT COUNT(*) n, ROUND(COALESCE(SUM(net_amount),0),2) s
                         FROM claims WHERE COALESCE(is_deleted,0)=0")->fetch_assoc();
check((int) $before['n'] === (int) $after['n']
      && abs((float) $before['s'] - (float) $after['s']) < 0.005,
      '★★ المستخلصاتُ **' . $before['n'] . ' بمجموع ' . $before['s']
      . '** قبلَ وبعد — «ما لا يُقرأ لا يغيّر رقمًا»');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('contract_operational_sites', array('where' => array('id' => $SC1)));
check($leak === null, '★ نطاقُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
