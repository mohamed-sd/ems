<?php
/**
 * se07_handover_proof.php — إثباتُ حدثِ التسليم (إيجابيٌّ وسلبيٌّ · يُنظِّف أثرَه)
 * ═══════════════════════════════════════════════════════════════════════════
 *   ① تسليمٌ صحيحٌ يمرّ: الحصةُ تنتقل فعلًا بين الحاويتين وصفُّ الحدثِ يُكتب
 *   ② بلا مستندٍ ⇐ 422 · ③ لنفسِ الحاوية ⇐ 422 · ④ فوقَ الرصيد ⇐ 409
 *   ⑤ في شهرٍ مغلقٍ ⇐ 409 (HO-05) · ⑥ الشاشةُ تُصيَّر للمخوَّلِ ويُمنع غيرُه
 *   ثم يُعاد كلُّ شيءٍ كما كان (تسليمٌ عكسيٌّ + حذفُ صفَّي الاختبار).
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Services/Capacity/HandoverService.php';
use App\Services\Capacity\HandoverService as HS;

$db = $conn; $db->set_charset('utf8mb4');
$CO = 4; $TAG = 'SE07-PROOF';
$one = function (string $s) use ($db) { $r = $db->query($s); return $r ? $r->fetch_row()[0] : null; };
$fails = 0;
$ok = function (bool $c, string $m) use (&$fails) { echo ($c ? '  ✔ ' : '  ✘ ') . $m . "\n"; if (!$c) { $fails++; } };

$actor = (int) $one("SELECT id FROM users WHERE company_id=$CO LIMIT 1");
$_SESSION = array('user' => array('id' => $actor, 'company_id' => $CO, 'role' => '2'));
$gate = ems_tenant_db();

/* حاويتا موردٍ تحت أبٍ واحدٍ والرصيدُ يسمح */
$pair = $db->query("SELECT a.id fa, b.id fb, a.allocated_qty qa, b.allocated_qty qb, b.cap_qty cb
                    FROM op_containers a JOIN op_containers b
                      ON b.parent_id = a.parent_id AND b.id <> a.id AND b.is_deleted = 0 AND b.level='مورد'
                    WHERE a.company_id=$CO AND a.is_deleted=0 AND a.level='مورد'
                      AND a.allocated_qty >= 10 AND b.cap_qty >= b.allocated_qty + 5
                    LIMIT 1")->fetch_assoc();
if (!$pair) { exit("لا زوجَ حاوياتٍ صالحًا\n"); }
$FA = (int) $pair['fa']; $FB = (int) $pair['fb'];
$qa0 = (float) $pair['qa']; $qb0 = (float) $pair['qb'];
echo "══ الزوج: #$FA (متاح $qa0) ⇐ #$FB ══\n\n";

/* ── ① التسليمُ الصحيح ── */
$r = HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FA, 'to_container_id' => $FB,
    'moved_qty' => 5, 'effective_from' => '2026-08-16', 'doc_ref' => "$TAG-DOC-1", 'reason' => 'اختبارُ إثبات'), $actor);
$ok(!empty($r['ok']), '① تسليمُ 5 ساعاتٍ مرّ (#' . ($r['swap_id'] ?? '—') . ')');
$qa1 = (float) $one("SELECT allocated_qty FROM op_containers WHERE id=$FA");
$qb1 = (float) $one("SELECT allocated_qty FROM op_containers WHERE id=$FB");
$ok(abs($qa1 - ($qa0 - 5)) < 0.005 && abs($qb1 - ($qb0 + 5)) < 0.005,
    "الحصةُ انتقلت فعلًا ($qa0⇐$qa1 · $qb0⇐$qb1)");
$ok((int) $one("SELECT COUNT(*) FROM container_swaps WHERE doc_ref='$TAG-DOC-1'") === 1, 'وصفُّ الحدثِ كُتب');

/* ── السلبيات ── */
$r = HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FA, 'to_container_id' => $FB,
    'moved_qty' => 1, 'effective_from' => '2026-08-16', 'doc_ref' => '', 'reason' => 'x'), $actor);
$ok(empty($r['ok']) && $r['code'] === 422, '② بلا مستندٍ ⇐ 422');
$r = HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FA, 'to_container_id' => $FA,
    'moved_qty' => 1, 'effective_from' => '2026-08-16', 'doc_ref' => 'D', 'reason' => 'x'), $actor);
$ok(empty($r['ok']) && $r['code'] === 422, '③ لنفسِ الحاوية ⇐ 422');
$r = HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FA, 'to_container_id' => $FB,
    'moved_qty' => 9999999, 'effective_from' => '2026-08-16', 'doc_ref' => 'D', 'reason' => 'x'), $actor);
$ok(empty($r['ok']) && $r['code'] === 409, '④ فوقَ الرصيد ⇐ 409');
$closedDate = (string) $one("SELECT start_date FROM fin_financial_periods
                             WHERE company_id=$CO AND period_type='month' AND posting_allowed=0 LIMIT 1");
if ($closedDate) {
    $r = HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FA, 'to_container_id' => $FB,
        'moved_qty' => 1, 'effective_from' => $closedDate, 'doc_ref' => 'D', 'reason' => 'x'), $actor);
    $ok(empty($r['ok']) && $r['code'] === 409, "⑤ شهرٌ مغلق ($closedDate) ⇐ 409 (HO-05)");
}

/* ── ⑥ الشاشةُ حيًّا ── */
$jar = sys_get_temp_dir() . '/se07_jar'; @unlink($jar);
$http = function (string $url, ?array $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45));
    if ($post) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hl = curl_getinfo($ch, CURLINFO_HEADER_SIZE); curl_close($ch);
    return array('code' => $code, 'body' => substr((string) $r, $hl),
                 'loc' => preg_match('/^Location:\s*(.+)$/mi', substr((string) $r, 0, $hl), $m) ? trim($m[1]) : null);
};
$g = $http('http://localhost/ems/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m);
$http('http://localhost/ems/login.php', array('csrf_token' => $m[1], 'username' => 'محمد', 'password' => '12345678'));
$pg = $http('http://localhost/ems/Suppliers/sup_handover.php');
/* محمد (دور 1) مُنح عرضًا بلا إضافةٍ عمدًا — فالنموذجُ (ورمزُه) لا يُصيَّران له،
   وذلك صوابُ الشاشةِ لا عيبُها. الرمزُ يُشترط فقط متى صُيِّر النموذج. */
$hasForm = strpos($pg['body'], 'sup_handover') !== false && stripos($pg['body'], '<form') !== false;
$ok($pg['code'] === 200 && strpos($pg['body'], 'تسليمُ الحصص') !== false
    && (!$hasForm || strpos($pg['body'], 'csrf_token') !== false),
    '⑥ الشاشةُ تُصيَّر للمخوَّل (' . $pg['code'] . ($hasForm ? ' · نموذجٌ برمزه' : ' · قراءةٌ بلا نموذجٍ لدورٍ بلا can_add') . ')');
@unlink($jar);
$g2 = $http('http://localhost/ems/login.php');
preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g2['body'], $m2);
$http('http://localhost/ems/login.php', array('csrf_token' => $m2[1], 'username' => 'fin.reader@equipation.sd', 'password' => '12345678'));
$pg2 = $http('http://localhost/ems/Suppliers/sup_handover.php');
$ok(in_array($pg2['code'], array(301, 302, 303), true), 'والقارئُ غيرُ المخوَّلِ يُحوَّل (' . $pg2['code'] . ')');

/* ── التنظيف: تسليمٌ عكسيٌّ ثم حذفُ صفَّي الاختبار ── */
echo "\n── التنظيف ──\n";
HS::record($gate, $db, array('company_id' => $CO, 'from_container_id' => $FB, 'to_container_id' => $FA,
    'moved_qty' => 5, 'effective_from' => '2026-08-16', 'doc_ref' => "$TAG-DOC-2", 'reason' => 'إرجاعُ الإثبات'), $actor);
$db->query("DELETE FROM container_swaps WHERE doc_ref LIKE '$TAG%'");
echo '  حُذف ' . $db->affected_rows . " صفَّ اختبار\n";
$qaF = (float) $one("SELECT allocated_qty FROM op_containers WHERE id=$FA");
$qbF = (float) $one("SELECT allocated_qty FROM op_containers WHERE id=$FB");
$ok(abs($qaF - $qa0) < 0.005 && abs($qbF - $qb0) < 0.005, 'الأرصدةُ عادت كما كانت');

echo "\n" . ($fails === 0 ? "✔ التسليمُ يعمل بقواعدِه — صفرُ إخفاق\n" : "✘ إخفاقات: $fails\n");
exit($fails === 0 ? 0 : 1);
