<?php
/**
 * tests/transfer_cost_and_shares_test.php — سطرُ التحميلِ بقيمٍ حيّةٍ ومركزٍ حقيقيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0084 · INJ-0306
 *
 * · 0084: «بعد الإقفال يوجد **صفٌّ واحدٌ** في `transfer_cost_lines` لهذا الأمر
 *   بقيمِ `cost_type` و`cost_bearer` **ضمن الـENUM** وبمبلغٍ مطابقٍ للمُدخَل؛
 *   **وعند تعمّدِ الفشل تُعرض رسالةُ خطأٍ لا رسالةُ نجاح**».
 * · 0306: «إقفالُ أمرين **لمشروعين مختلفين** يُنتج سطرَي تكلفةٍ **بمركزَي تكلفةٍ
 *   مختلفين** مطابقين لعمود `transfer_orders.analytic_cost_center`».
 *
 * ── العلّةُ التي يحرسها ───────────────────────────────────────────────────
 * كان السطرُ يُكتب بـ`cost_type='actual_total'` و`cost_bearer='project'` —
 * **وكلتاهما خارج تعدادِها**. و`sql_mode` خالٍ، فالخارجُ **يُبتر إلى `''`
 * بتحذير 1265 لا بخطأ**: يمضي الإقفالُ مُعلنًا النجاحَ ودفترُ التحميلِ خاوٍ.
 * و`analytic_cost_center` كان السلسلةَ الحرفيةَ `'PRJ'` لكلِّ أمرٍ مهما اختلف
 * مشروعُه. **و٩ أسطرٍ مقيسةٍ في القاعدةِ بهذه الحال** — موروثٌ يُعلَن ولا يُخفى.
 *
 * ◆ والشاهدُ يبذر أمرين **لمشروعين مختلفين** بوسمٍ عائليٍّ ثابت، ويُقفلهما،
 *   ويقرأ السطرين — ثم يكنس الأبناءَ قبل الآباءِ ويفحص مُرجَعَ كلِّ حذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Transport/TransferDeliveryService.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'TRSCOST-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ سطرُ التحميلِ بقيمٍ حيّةٍ ومركزٍ حقيقيّ (INJ-0084 · INJ-0306)');
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'شاهد');

/* ── الكنسُ: الأبناءُ قبل الآباء · ويُفحص المُرجَع ───────────────────────── */
$sweep = function () use ($conn, $TAG, $CO) {
    $ids = array();
    $r = $conn->query("SELECT id FROM transfer_orders WHERE company_id={$CO} AND order_no LIKE '%{$TAG}%'");
    while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
    foreach ($ids as $id) {
        $conn->query("DELETE FROM transfer_cost_lines WHERE order_id={$id}");
        $conn->query("DELETE FROM transfer_delivery_docs WHERE order_id={$id}");
        $conn->query("DELETE FROM transfer_events WHERE order_id={$id}");
        $conn->query("DELETE FROM transfer_lines WHERE order_id={$id}");
        $conn->query("DELETE FROM processed_operations WHERE doc_type='transfer_order' AND doc_id={$id}");
    }
    $d = $conn->query("DELETE FROM transfer_orders WHERE company_id={$CO} AND order_no LIKE '%{$TAG}%'");
    if ($d === false) { return -1; }
    $r = $conn->query("SELECT COUNT(*) FROM transfer_orders WHERE company_id={$CO} AND order_no LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweep() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');

/* ── البذر: أمران لمشروعين مختلفين بمركزَي تكلفةٍ مختلفين ────────────────── */
$projects = array();
$r = $conn->query("SELECT id FROM project WHERE company_id={$CO} ORDER BY id LIMIT 2");
while ($r && ($x = $r->fetch_row())) { $projects[] = (int) $x[0]; }
$ok(count($projects) === 2, 'مشروعان حقيقيّان: ' . implode(' · ', $projects));

$tt = 0;
$r = $conn->query("SELECT id FROM transfer_types WHERE company_id={$CO} ORDER BY id LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $tt = (int) $x[0]; }
$ok($tt > 0, 'نوعُ ترحيلٍ قائمٌ #' . $tt);

/* المواقعُ حقيقيةٌ — `from_location_id`/`to_location_id` بمفتاحٍ أجنبيٍّ لا يُخدع */
$locs = array();
$r = $conn->query('SELECT id FROM trs_locations ORDER BY id LIMIT 2');
while ($r && ($x = $r->fetch_row())) { $locs[] = (int) $x[0]; }
$ok(count($locs) === 2, 'موقعان حقيقيّان للترحيل: ' . implode(' · ', $locs));

$made = array();
foreach ($projects as $i => $pid) {
    $no     = 'TRS-' . $TAG . '-' . ($i + 1);
    $bearer = $i === 0 ? 'client' : 'company';
    $center = 'CC-' . $TAG . '-' . ($i + 1);
    $ins = $conn->query("INSERT INTO transfer_orders
            (company_id, order_no, transfer_type_id, direction, project_id, request_date,
             from_location_id, to_location_id,
             stage, cost_bearer, analytic_cost_center, tariff_currency, created_by, created_at)
          VALUES ({$CO}, '{$no}', {$tt}, 'internal', {$pid}, CURDATE(),
                  {$locs[0]}, {$locs[1]},
                  'arrived', '{$bearer}', '{$center}', 'USD', 1, NOW())");
    if (!$ins) { $ok(false, 'بذرُ الأمر ' . ($i + 1), $conn->error); continue; }
    $oid = (int) $conn->insert_id;
    /* الحارسُ الترتيبيُّ يشترط مستندَ تسليمٍ وبندًا واحدًا على الأقل */
    $conn->query("INSERT INTO transfer_delivery_docs (company_id, order_id, doc_ref, witness_name, delivered_at, created_by, created_at)
                  VALUES ({$CO}, {$oid}, 'DOC-{$TAG}-" . ($i + 1) . "', 'شاهد', NOW(), 1, NOW())");
    $conn->query("INSERT INTO transfer_lines (company_id, order_id, item_type, quantity, note)
                  VALUES ({$CO}, {$oid}, 'equipment', 1, '{$TAG}')");
    $made[] = array('id' => $oid, 'no' => $no, 'project' => $pid, 'bearer' => $bearer, 'center' => $center);
}
$ok(count($made) === 2, 'بُذر أمران لمشروعين مختلفين: ' . count($made));

/* ── الإقفالُ وقراءةُ السطر ──────────────────────────────────────────────── */
$say("\n── الإقفالُ وقراءةُ سطرِ التحميل");
$svc = new \App\Services\Transport\TransferDeliveryService($conn);
$lines = array();
foreach ($made as $k => $m) {
    $amount = 1000 + $k;
    $res = $svc->closeWithCost($CO, (int) $m['id'], (float) $amount, 1);
    $ok(!empty($res['ok']) && empty($res['replay']),
        'أُقفل ' . $m['no'] . ': ' . mb_substr((string) $res['msg'], 0, 80));
    $q = $conn->query("SELECT COUNT(*) n FROM transfer_cost_lines WHERE order_id=" . (int) $m['id']);
    $n = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
    $ok($n === 1, '«**صفٌّ واحدٌ** في `transfer_cost_lines` لهذا الأمر»: ' . $n);
    $q = $conn->query("SELECT cost_type, cost_bearer, analytic_cost_center, amount_usd, amount_local, currency
                         FROM transfer_cost_lines WHERE order_id=" . (int) $m['id'] . ' LIMIT 1');
    $l = $q ? $q->fetch_assoc() : null;
    $lines[] = $l;
    $ok($l && (string) $l['cost_type'] !== '', 'و`cost_type` غيرُ مبتور: «' . ($l ? $l['cost_type'] : '?') . '»');
    $ok($l && (string) $l['cost_bearer'] === $m['bearer'],
        'و`cost_bearer` من الأمرِ لا من قيمةٍ ثابتة: «' . ($l ? $l['cost_bearer'] : '?') . '»');
    $ok($l && abs((float) $l['amount_usd'] - $amount) < 0.005,
        'وبمبلغٍ مطابقٍ للمُدخَل: ' . ($l ? $l['amount_usd'] : '?') . ' = ' . $amount);
    $ok($l && (string) $l['analytic_cost_center'] === $m['center'],
        'و«مركزُ تكلفةٍ مطابقٌ لعمود `transfer_orders.analytic_cost_center`»: «' . ($l ? $l['analytic_cost_center'] : '?') . '»');
}

/* والقيمُ **ضمن التعدادِ الحيِّ** — تُقرأ من `SHOW COLUMNS` لا من الذاكرة */
$enumOf = function ($col) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM transfer_cost_lines LIKE '{$col}'");
    if (!$r || !($x = $r->fetch_assoc())) { return array(); }
    return preg_match('~^enum\((.*)\)$~is', (string) $x['Type'], $m) ? explode("','", trim($m[1], "'")) : array();
};
$eT = $enumOf('cost_type'); $eB = $enumOf('cost_bearer');
$bad = array();
foreach ($lines as $l) {
    if (!$l) { $bad[] = '(لا سطر)'; continue; }
    if (!in_array((string) $l['cost_type'], $eT, true))   { $bad[] = 'type:' . $l['cost_type']; }
    if (!in_array((string) $l['cost_bearer'], $eB, true)) { $bad[] = 'bearer:' . $l['cost_bearer']; }
}
$ok(!$bad, '«بقيمِ `cost_type` و`cost_bearer` **ضمن الـENUM**» — مقروءًا حيًّا', implode(' · ', $bad));

/* ── 0306: مركزان مختلفان لمشروعين مختلفين ─────────────────────────────── */
$say("\n── مشروعان مختلفان ⇒ مركزان مختلفان");
$c1 = isset($lines[0]) && $lines[0] ? (string) $lines[0]['analytic_cost_center'] : '';
$c2 = isset($lines[1]) && $lines[1] ? (string) $lines[1]['analytic_cost_center'] : '';
$ok($c1 !== '' && $c2 !== '' && $c1 !== $c2,
    '«سطرَي تكلفةٍ **بمركزَي تكلفةٍ مختلفين**»: «' . $c1 . '» ≠ «' . $c2 . '»');
$ok($c1 !== 'PRJ' && $c2 !== 'PRJ', 'ولا أثرَ للقيمةِ النائبةِ `PRJ`');

/* ── وعند تعمّدِ الفشل: رسالةُ خطأٍ لا رسالةُ نجاح ──────────────────────── */
$say("\n── «وعند تعمّدِ الفشل تُعرض رسالةُ خطأٍ لا رسالةُ نجاح»");
$noBearer = 'TRS-' . $TAG . '-NB';
$conn->query("INSERT INTO transfer_orders
        (company_id, order_no, transfer_type_id, direction, project_id, request_date,
         from_location_id, to_location_id,
         stage, cost_bearer, analytic_cost_center, created_by, created_at)
      VALUES ({$CO}, '{$noBearer}', {$tt}, 'internal', {$projects[0]}, CURDATE(),
              {$locs[0]}, {$locs[1]},
              'arrived', NULL, NULL, 1, NOW())");
$nbId = (int) $conn->insert_id;
$conn->query("INSERT INTO transfer_delivery_docs (company_id, order_id, doc_ref, witness_name, delivered_at, created_by, created_at)
              VALUES ({$CO}, {$nbId}, 'DOC-{$TAG}-NB', 'شاهد', NOW(), 1, NOW())");
$conn->query("INSERT INTO transfer_lines (company_id, order_id, item_type, quantity, note)
              VALUES ({$CO}, {$nbId}, 'equipment', 1, '{$TAG}')");
$res = $svc->closeWithCost($CO, $nbId, 500.0, 1);
$ok(empty($res['ok']), 'أمرٌ بلا متحمِّلٍ ولا مركزٍ **يُردُّ**: ' . mb_substr((string) $res['msg'], 0, 90));
$q = $conn->query("SELECT COUNT(*) FROM transfer_cost_lines WHERE order_id={$nbId}");
$nbN = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
$ok($nbN === 0, 'ولم يُكتب سطرُ تحميلٍ للأمرِ المردود: ' . $nbN);
$q = $conn->query("SELECT stage FROM transfer_orders WHERE id={$nbId}");
$st = ($q && ($x = $q->fetch_row())) ? (string) $x[0] : '';
$ok($st === 'arrived', 'ولم يُقفل الأمرُ — المعاملةُ ارتدَّت كاملةً (الحالة: ' . $st . ')');

/* ── الموروثُ يُعلَن ولا يُخفى ──────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) FROM transfer_cost_lines WHERE cost_type = '' OR cost_bearer = ''");
$legacy = ($q && ($x = $q->fetch_row())) ? (int) $x[0] : -1;
$ok($legacy <= 9,
    'وأسطرُ الموروثِ المبتورةُ لا تزيد (المُعلَن: ٩ · المقيسُ الآن: ' . $legacy . ')',
    'زادت عن الموروثِ المُعلَن');

$say("\n── الكنسُ البعديّ");
$left = $sweep();
$ok($left === 0, 'كُنست عائلةُ الوسمِ كاملةً — الأبناءُ قبل الآباء', 'المتبقّي=' . $left);

$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
