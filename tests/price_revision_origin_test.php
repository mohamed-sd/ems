<?php
/**
 * tests/price_revision_origin_test.php — منشأُ مراجعةِ السعرِ وحارسُ الفصل
 * ═══════════════════════════════════════════════════════════════════════════
 * **ما كُشف وقيس**: حارسُ «من أنشأ لا يعتمد» في `PriceAdjustmentService::approve()`
 * كان شرطُه `created_by > 0` — فيسكت على كلِّ صفٍّ نُلِّ المُنشئ. والكرونُ
 * (`cron_price_adjustment.php:49`) يمرّر `actor = 0` فيُكتب النُّلُّ، فحارسٌ قائمٌ
 * نصًّا غائبٌ فعلًا على كلِّ ما يُنشئه الكرون. وأخطرُ منه: `approved_by` كان
 * `(int)$actor ?: null` — أي **«اعتُمد» بمعتمِدٍ نُلٍّ: أثرٌ بلا صاحب**.
 *
 * ── وما أُغلق بالضبطِ (ولا يُدَّعى أكثر) ────────────────────────────────────
 *   ✔ لا اعتمادَ بلا معتمِدٍ مُعرَّفٍ — رُدَّ في الخدمةِ **وفي القاعدة**.
 *   ✔ لا صفَّ «إنسانيَّ المنشإ» بلا معرِّفٍ — فجلسةٌ مكسورةٌ بـ`uid=0` لم تعد
 *     تُنتج صفًّا يُعطِّل الحارسَ صامتًا.
 *   ✔ النُّلُّ صار يعني «آليٌّ **مُصرَّحٌ**» لا «مجهولٌ سكتنا عنه».
 *   ○ **ولا يُدَّعى** أنَّ صفَّ الكرونِ صار محروسًا بالفصل: لا إنسانَ أنشأه،
 *     فاعتمادُ إنسانٍ له **هو** العينُ الثانيةُ لا رُبتَ يدٍ على عملِ نفسِه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/app/Services/Contract/PriceAdjustmentService.php';

use App\Services\Contract\PriceAdjustmentService as PAS;

$conn = $GLOBALS['conn'];
$CO = 4;
/* ◆ **بلا جلسةٍ لا يرى البوّابُ صفًّا**: `ems_tenant_db()` يبني نطاقَه من
     $_SESSION، فبدونها ردَّت `approve()` بـ404 «غيرُ موجودةٍ في نطاقك» —
     فمرَّ فرعي الموجبُ خواءً. تُضبَط الجلسةُ صراحةً، ويُشترط ألّا يكون الردُّ 404. */
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => $CO, 'name' => 'origin test');
$T = 'contract_price_revisions';
$FAMILY = 'PRO';
$MARK = $FAMILY . getmypid();
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };

/* كنسُ عائلةِ الوسمِ قبل البدء — ثغرةُ جولةٍ ماتت تُعمي التالية */
$conn->query("DELETE FROM {$T} WHERE period_key LIKE '{$FAMILY}%'");
$say("══ منشأُ مراجعةِ السعرِ وحارسُ الفصل  (كُنس {$conn->affected_rows} من عائلةِ {$FAMILY})");

/* ══ ① العمودُ والقيدان قائمان ═════════════════════════════════════════════ */
$cols = array();
$r = $conn->query("SHOW COLUMNS FROM {$T}");
while ($r && ($x = $r->fetch_assoc())) { $cols[$x['Field']] = $x; }
$ok(isset($cols['created_origin']), 'عمودُ created_origin قائم');
$ok(isset($cols['created_origin']) && stripos($cols['created_origin']['Type'], "'system'") !== false,
    "ونوعُه يحوي 'system'");
$ok(isset($cols['created_origin']) && $cols['created_origin']['Null'] === 'NO',
    'وهو NOT NULL — فلا صفَّ بلا تصريحِ منشإ');
$chk = array();
$r = $conn->query("SELECT CONSTRAINT_NAME c FROM information_schema.CHECK_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                      AND CONSTRAINT_NAME IN ('chk_price_rev_origin_actor','chk_price_rev_approver_known')");
while ($r && ($x = $r->fetch_assoc())) { $chk[$x['c']] = true; }
$ok(isset($chk['chk_price_rev_origin_actor']), 'قيدُ chk_price_rev_origin_actor قائم');
$ok(isset($chk['chk_price_rev_approver_known']), 'قيدُ chk_price_rev_approver_known قائم');

/* ══ ② القاعدةُ تردُّ فعلًا — لا زخرفةَ قيدٍ ════════════════════════════════
     ويُشترط **رمزُ خطإِ القيدِ** (4025 MariaDB · 3819 MySQL) لا مجرَّدُ الردِّ،
     لأنَّ أوّلَ جسٍّ لي رُدَّ بمفتاحٍ أجنبيٍّ فقرأتُه «القيدُ يعمل» وهو لم يُبلَغ. */
$term = $conn->query("SELECT id, contract_id, contract_item_id FROM contract_price_terms LIMIT 1");
$term = $term ? $term->fetch_assoc() : null;
$ok($term !== null, 'وُجد بندُ تسعيرٍ حقيقيٌّ للجسّ (لا رقمٌ مخترَع)');
if ($term !== null) {
    $tid = (int) $term['id']; $cid = (int) $term['contract_id']; $iid = (int) $term['contract_item_id'];
    $ins = function ($pk, $createdBy, $origin, $approvedAt = null, $approvedBy = null)
           use ($conn, $T, $CO, $tid, $cid, $iid) {
        $cb = $createdBy === null ? 'NULL' : (int) $createdBy;
        $aa = $approvedAt === null ? 'NULL' : "'" . $conn->real_escape_string($approvedAt) . "'";
        $ab = $approvedBy === null ? 'NULL' : (int) $approvedBy;
        $conn->query("INSERT INTO {$T} (company_id,term_id,contract_id,contract_item_id,period_key,
            as_of_date,effective_from,outcome,created_by,created_origin,approved_at,approved_by,created_at)
            VALUES ({$CO},{$tid},{$cid},{$iid},'{$pk}','2026-01-01','2026-01-01','amended',
                    {$cb},'{$origin}',{$aa},{$ab},NOW())");
        return $conn->errno;
    };
    $isChk = function ($e) { return $e === 4025 || $e === 3819; };

    $e = $ins($MARK . 'A', null, 'user');
    $ok($isChk($e), 'القاعدةُ تردُّ «إنسانًا بلا معرِّفٍ» بقيدها (خطأ ' . $e . ')',
        'لو مرَّ لعاد الالتباسُ من بابِ الإدراجِ الخام');
    $e = $ins($MARK . 'B', 7, 'system');
    $ok($isChk($e), 'وتردُّ «آلةً بمعرِّفِ إنسانٍ» (خطأ ' . $e . ')');
    $e = $ins($MARK . 'C', null, 'system', date('Y-m-d H:i:s'), null);
    $ok($isChk($e), 'وتردُّ «اعتُمد بلا معتمِدٍ» (خطأ ' . $e . ')');
    /* والفرعُ الموجبُ — بغيرِه تكون الثلاثةُ أعلاه ردًّا لكلِّ شيءٍ لا تمييزًا */
    $e = $ins($MARK . 'D', null, 'system');
    $ok($e === 0, 'وتمرُّ «آلةٌ بلا معرِّفٍ» — فالقيدُ يميّز ولا يردُّ الكلَّ (خطأ ' . $e . ')');
    $conn->query("DELETE FROM {$T} WHERE period_key LIKE '{$FAMILY}%'");
}

/* ══ ③ الخدمةُ تردُّ التصريحَ المتناقضَ ومنشأَ الإنسانِ بلا معرِّف ═══════════ */
$gate = ems_tenant_db();
$aContract = $conn->query("SELECT id FROM contracts WHERE company_id = {$CO} LIMIT 1");
$aContract = $aContract ? (int) $aContract->fetch_row()[0] : 0;
$ok($aContract > 0, 'وُجد عقدٌ للنداء');
$r1 = PAS::applyDue($conn, $gate, $CO, $aContract, '2026-06-30', 0, 'user');
$ok(empty($r1['ok']) && (int) (isset($r1['code']) ? $r1['code'] : 0) === 403,
    'applyDue تردُّ فعلَ إنسانٍ بلا معرِّفٍ (403)',
    'كودٌ ' . (isset($r1['code']) ? $r1['code'] : '—'));
$r2 = PAS::applyDue($conn, $gate, $CO, $aContract, '2026-06-30', 7, 'system');
$ok(empty($r2['ok']) && (int) (isset($r2['code']) ? $r2['code'] : 0) === 422,
    'وتردُّ منشأً آليًّا بمعرِّفِ إنسانٍ (422)',
    'كودٌ ' . (isset($r2['code']) ? $r2['code'] : '—'));
/* الفرعُ الموجب: منشأٌ متّسقٌ لا يُردُّ بهذين الحارسين */
$r3 = PAS::applyDue($conn, $gate, $CO, $aContract, '2026-06-30', 0, 'system');
$ok(!isset($r3['code']) || !in_array((int) $r3['code'], array(403, 422), true),
    'ولا تردُّ منشأً آليًّا متّسقًا — فالحارسان يميّزان',
    'كودٌ ' . (isset($r3['code']) ? $r3['code'] : '—'));

/* ══ ④ حارسُ الاعتماد — على صفٍّ حقيقيٍّ يُزرَع ويُرفع ═══════════════════════ */
if ($term !== null) {
    $tid = (int) $term['id']; $cid = (int) $term['contract_id']; $iid = (int) $term['contract_item_id'];
    $creator = 888;
    $conn->query("INSERT INTO {$T} (company_id,term_id,contract_id,contract_item_id,period_key,
        as_of_date,effective_from,outcome,new_price,created_by,created_origin,created_at)
        VALUES ({$CO},{$tid},{$cid},{$iid},'{$MARK}X','2026-01-01','2026-01-01','amended',
                100.0000,{$creator},'user',NOW())");
    $rid = (int) $conn->insert_id;
    $ok($rid > 0, 'زُرعت مراجعةٌ بشريةُ المنشإِ (مُنشئُها ' . $creator . ')', $conn->error);
    if ($rid > 0) {
        $a = PAS::approve($conn, $gate, $CO, $rid, 0);
        $ok((int) (isset($a['code']) ? $a['code'] : 0) === 403 && empty($a['ok']),
            'approve تردُّ اعتمادًا بلا معتمِدٍ مُعرَّفٍ (403)',
            'كودٌ ' . (isset($a['code']) ? $a['code'] : '—') . ' · ' . (isset($a['reason']) ? $a['reason'] : ''));
        $b = PAS::approve($conn, $gate, $CO, $rid, $creator);
        $ok((int) (isset($b['code']) ? $b['code'] : 0) === 403 && empty($b['ok']),
            'وتردُّ اعتمادَ المُنشئِ نفسِه — «من أنشأ لا يعتمد» (403)',
            'كودٌ ' . (isset($b['code']) ? $b['code'] : '—'));
        /* الفرعُ الموجب: معتمِدٌ آخرُ لا يُردُّ **بهذين** — وإن رُدَّ فبسببٍ آخرَ يُعلَن */
        $c = PAS::approve($conn, $gate, $CO, $rid, $creator + 1);
        $blockedBySep = (int) (isset($c['code']) ? $c['code'] : 0) === 403;
        $notFound = (int) (isset($c['code']) ? $c['code'] : 0) === 404;
        $ok(!$notFound, 'والصفُّ مرئيٌّ للبوّابِ — وإلا فكلُّ فروعِ ④ خواء',
            'كودٌ ' . (isset($c['code']) ? $c['code'] : '—'));
        $ok(!$blockedBySep && !$notFound, 'ومعتمِدٌ آخرُ لا يُردُّ بحارسِ الفصل',
            'كودٌ ' . (isset($c['code']) ? $c['code'] : '—') . ' · ' . (isset($c['reason']) ? $c['reason'] : ''));
        if (!empty($c['ok'])) {
            $row = $conn->query("SELECT approved_by FROM {$T} WHERE id = {$rid}");
            $ab = $row ? $row->fetch_assoc()['approved_by'] : null;
            $ok($ab !== null && (int) $ab > 0, 'والمعتمِدُ سُجِّل بمعرِّفٍ موجبٍ (' . var_export($ab, true) . ')');
        } else {
            $say('  ○ لم يكتمل الاعتمادُ لسببٍ آخرَ (' . (isset($c['reason']) ? $c['reason'] : '') . ') — لا يُحكَم على تسجيلِ المعتمِد');
        }
        $conn->query("DELETE FROM {$T} WHERE id = {$rid}");
    }
}

/* ══ ⑤ الكرونُ يُصرّح — ولا يُترك التصريحُ للحدس ════════════════════════════ */
$cronSrc = file_get_contents($ROOT . '/Contracts/cron_price_adjustment.php');
$ok(strpos($cronSrc, "applyDue(\$conn, \$gate, \$co, \$cid, \$date, 0, 'system')") !== false,
    "الكرونُ يمرّر origin='system' صراحةً");
$svcSrc = file_get_contents($ROOT . '/app/Services/Contract/PriceAdjustmentService.php');
$ok(strpos($svcSrc, "'created_origin' => \$origin") !== false,
    'والخدمةُ تكتب المنشأَ في العمود');
$ok(!preg_match("~if \(\(int\) \\\$rev\['created_by'\] > 0 && \(int\) \\\$rev\['created_by'\] === \(int\) \\\$actor\)~", $svcSrc),
    'والشرطُ القديمُ (created_by > 0) مرفوعٌ من الحارس',
    'بقاؤه يعني أنَّ الحارسَ ما زال يسكت على نُلِّ المُنشئ');
$ok(strpos($svcSrc, "اعتمادٌ بلا معتمِدٍ مُعرَّفٍ") !== false, 'ورَدُّ الاعتمادِ المجهولِ مكتوبٌ في الخدمة');

/* كنسٌ ختاميٌّ */
$conn->query("DELETE FROM {$T} WHERE period_key LIKE '{$FAMILY}%'");
$tail = $conn->affected_rows;
$ok($tail === 0, 'صفرُ ثغرةٍ من عائلةِ الوسمِ بعد الجولة (' . $tail . ')');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
