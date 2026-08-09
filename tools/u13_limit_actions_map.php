<?php
/**
 * tools/u13_limit_actions_map.php — وصلُ الحدودِ الصريحةِ بالأفعالِ التي تمنعها
 * ═══════════════════════════════════════════════════════════════════════════
 * الحدُّ «لا يملك محاسبُ الإدارة: اعتمادَ طلبِ الإدارةِ النهائي» يمنع **فعلًا
 * بعينِه**. وبلا رمزِ فعلٍ يقابله يبقى نصًّا لا يُقاس عليه — فلا العاملُ
 * الحاديَ عشرَ يشتقُّ منه، ولا حارسٌ يستطيع تنفيذَه.
 *
 * ◆ ولا يُوصَل حدٌّ برمزٍ إلا إن كان الرمزُ **مسجَّلًا فعلًا** في قاموسِ
 *   الأفعال — فربطُ حدٍّ برمزٍ لا وجودَ له يصنع منعًا لا يقع أبدًا.
 *
 * ◆ وما لا فعلَ مسجَّلًا يقابله يبقى `action_codes` فارغًا: فجوةٌ ظاهرةٌ
 *   تُعرض في التقرير، لا سكوتٌ يُقرأ اكتمالًا.
 *
 * التشغيل: php tools/u13_limit_actions_map.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
require_once $ROOT . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST', '127.0.0.1'), ems_env('DB_USER', 'root'),
                 ems_env('DB_PASS', ''), ems_env('DB_NAME', 'ems'), (int) ems_env('DB_PORT', '3306'));
$db->set_charset('utf8mb4');

/* ═══ الفرقُ الحاكم: منعٌ مطلقٌ أم منعٌ مشروط؟ ════════════════════════════
   ◆ **المطلق**: «لا يملك محاسبُ الإدارة: تنفيذَ الدفع» — لا حالَ يجوز فيها.
     فيُوصَل برمزِ فعلٍ ويصير المنعُ نقضًا في اشتقاقِ الصلاحية.
   ◆ **المشروط**: «إغلاقُ ملاحظاتِه **بلا دليل**» · «تنفيذُ التحويلِ **الذي
     اعتمده**» · «إغلاقُ ملاحظةٍ **تخصُّه**». هذه لا تُوصَل: الشرطُ يعرفه
     المحرّكُ المالكُ وحدَه، ووصلُها منعًا مطلقًا **يقلب الحكم** فيمنع
     المراجعَ من إغلاقِ أيِّ ملاحظةٍ ولو بدليلٍ قَبِله — وهو نقيضُ الوثيقة.
   فلكلِّ حدٍّ هنا: نوعُه · ورمزُه إن كان مطلقًا · أو مُنفِذُه إن كان مشروطًا. */
$MAP = array(
    /* ── محاسبُ الإدارة (18): يُعِدُّ ولا يعتمد ولا ينفّذ ───────────────── */
    'FIN-ACC-01|LIMIT-01'  => array('blanket', 'fin.approve.need,fin.approve.budget'),
    'FIN-ACC-01|LIMIT-02'  => array('blanket', 'fin.approve.commit'),
    'FIN-ACC-01|LIMIT-03'  => array('blanket', 'fin.approve.execute'),
    'FIN-ACC-01|LIMIT-06'  => array('cond',    'ApprovalGate — «أعده بنفسه» شرطٌ يعرفه سجلُّ الإعداد'),
    'FIN-ACC-01|LIMIT-11'  => array('blanket', 'fin.approve.commit'),

    /* ── رئيسُ الحسابات (31): يعتمد ولا ينفّذ ما اعتمده ─────────────────── */
    'FIN-CTRL-01|LIMIT-01' => array('cond',    'ApprovalGate — «الذي اعتمده» شرطٌ على السلسلةِ لا على الدور'),
    'FIN-CTRL-01|LIMIT-05' => array('cond',    'ApprovalGate — «أعده بنفسه بلا مراجعةٍ مستقلة»'),
    'FIN-CTRL-01|LIMIT-06' => array('blanket', 'fin.approve.commit'),
    'FIN-CTRL-01|LIMIT-07' => array('cond',    'InternalAuditService — «تخصُّه بنفسه» شرطٌ على المُراجَع'),

    /* ── القيادةُ المالية (19,32): سلطةٌ بسقفٍ لا فوقَه ──────────────────── */
    'FIN-MGR-01|LIMIT-01'  => array('cond',    'ApprovalGate — «ما تجاوز السقف» شرطٌ على المبلغِ لا على الدور'),
    'FIN-MGR-01|LIMIT-02'  => array('cond',    'ApprovalGate — «دفعٌ اعتمده» شرطٌ على السلسلة'),
    'FIN-MGR-01|LIMIT-04'  => array('cond',    'ApprovalGate — «نيابةً عن» شرطٌ على الفاعلِ لا على الفعل'),
    'FIN-MGR-01|LIMIT-05'  => array('cond',    'InternalAuditService — «تخصُّه»'),
    'FIN-MGR-01|LIMIT-08'  => array('cond',    'AssignmentGate — «بلا موافقةِ الرئيس» شرطٌ يقع في البوابة'),

    /* ── الخزينةُ والبنوك (21,34,35): تنفّذ ولا تنشئ ولا تعتمد ──────────── */
    'FIN-TRE-01|LIMIT-02'  => array('blanket', 'fin.approve.need,fin.approve.commit'),
    'FIN-TRE-01|LIMIT-06'  => array('cond',    'ApprovalGate — «تجاوزُ سقفِ الدفع» شرطٌ على المبلغ'),

    /* ── المراجعُ الداخلي (33): يقرأ ولا يعتمد ولا ينفّذ ─────────────────── */
    'IAF-01|LIMIT-03'      => array('blanket', 'fin.approve.commit'),
    'IAF-01|LIMIT-04'      => array('blanket', 'fin.approve.execute'),
    'IAF-01|LIMIT-13'      => array('cond',    'InternalAuditService — «بلا دليل» شرطٌ يفحصه الإغلاق'),
);

/** أمسجَّلٌ الرمزُ في قاموسِ الأفعال؟ */
function registered(\mysqli $db, $code)
{
    static $cache = array();
    if (isset($cache[$code])) { return $cache[$code]; }
    $st = $db->prepare("SELECT 1 FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    if (!$st) { throw new RuntimeException($db->error); }
    $st->bind_param('s', $code);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $cache[$code] = $ok;
}

$bad = array(); $done = 0; $blanket = 0; $cond = 0;
$up = $APPLY ? $db->prepare("UPDATE gov_authority_limits SET action_codes = ?, enforced_by = IF(? = '', enforced_by, ?)
                              WHERE doc_code = ? AND code = ? AND active = 1") : null;
if ($APPLY && !$up) { exit("prepare: " . $db->error . "\n"); }

foreach ($MAP as $key => $spec) {
    list($kind, $payload) = $spec;
    list($doc, $lim) = explode('|', $key, 2);

    $exists = (int) $db->query("SELECT COUNT(*) FROM gov_authority_limits
                                 WHERE doc_code = '" . $db->real_escape_string($doc) . "'
                                   AND code = '" . $db->real_escape_string($lim) . "' AND active = 1")->fetch_row()[0];
    if ($exists === 0) { $bad[] = "$key — حدٌّ غيرُ موجود"; continue; }

    $codes = ''; $enf = '';
    if ($kind === 'blanket') {
        $list = array_filter(array_map('trim', explode(',', $payload)));
        foreach ($list as $cd) {
            if (!registered($db, $cd)) { $bad[] = "$key ⇦ $cd غيرُ مسجَّلٍ في القاموس"; }
        }
        $codes = implode(',', $list);
        $blanket++;
        printf("  مطلق   %-24s ⇦ %s\n", $key, $codes);
    } else {
        /* ◆ المشروطُ **لا يُوصَل** برمزٍ — ويُسجَّل مُنفِذُه بدلًا من ذلك. */
        $enf = mb_substr($payload, 0, 200);
        $cond++;
        printf("  مشروط  %-24s ⇠ %s\n", $key, mb_substr($payload, 0, 62));
    }

    if ($APPLY) {
        $up->bind_param('sssss', $codes, $enf, $enf, $doc, $lim);
        if (!$up->execute()) { $bad[] = "$key — " . $up->error; } else { $done++; }
    }
}
if ($APPLY) { $up->close(); }

$all    = (int) $db->query("SELECT COUNT(*) FROM gov_authority_limits WHERE active=1")->fetch_row()[0];
$mapped = (int) $db->query("SELECT COUNT(*) FROM gov_authority_limits WHERE active=1 AND action_codes<>''")->fetch_row()[0];
echo "\n" . str_repeat('─', 74) . "\n";
printf("  الحدود %d · منعٌ مطلقٌ موصولٌ بفعل %d · منعٌ مشروطٌ بمُنفِذِه %d · بلا وصلٍ %d\n",
       $all, $mapped, $cond, $all - $mapped - $cond);
echo "  ◆ «بلا وصل» حدودٌ لا يقابلها فعلٌ **مبنيٌّ** بعدُ — تُعرض فجوةً ولا تُخفى.\n";
if ($bad) { echo "\n  ✘ مشكلات:\n"; foreach ($bad as $b) { echo "     $b\n"; } }
echo $APPLY ? "  كُتب: $done\n" : "  (معاينة — أضف --apply)\n";
exit($bad ? 1 : 0);
