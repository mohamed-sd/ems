<?php
/**
 * tests/injfrd66_w5_test.php — شاهدُ الموجةِ ⑤: بلوغُ ما أُخفي · والخمسةُ المحسومة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إيجابيٌّ ①**: 113 دعوى بلوغٍ تصمد للقياسِ المستقل — صفرُ دعوى ساقطة.
 * ◆ **سالبٌ ②**: وبوابةُ البلوغِ **ترسُب** بدعوى مزروعةٍ لا سندَ لها — فصفرُها
 *   رؤيةٌ لا عمًى.
 * ◆ **سالبٌ ③**: و**لا تُخضِّر** بمطابقةٍ رخوةٍ للاسم: «contracts_details.php»
 *   سلسلةٌ داخل «supplierscontracts_details.php» — والمطابقةُ بالحدِّ ترفضها.
 * ◆ **إيجابيٌّ ④**: المنظرانِ قائمانِ بحقوقِ المستدعي وترتيبٍ موحَّد.
 * ◆ **سالبٌ ⑤**: و«يشمل أشهرَ الصفر» **قدرةٌ مُثبَتةٌ بثغرةٍ مصطنعة** — لا
 *   بعدِّ صفرٍ في بياناتٍ متّصلة. صفرُ شهرِ صفرٍ لا ينفي القدرة.
 * ◆ **سالبٌ ⑥**: و`SUP-13` **لا يُقاس بالطوابعِ الزمنية** — الشاهدُ يُثبت أنَّ
 *   المقياسَ الزمنيَّ يعطي 18 من 18 «خرقًا» وهو رقمٌ **مقلوبٌ تمامًا**.
 * ◆ **محجوزٌ ⑦**: أحمرانِ مقيسانِ بسببَين مكتوبَين — والبوابةُ تُبقيهما ظاهرَين.
 *
 * التشغيل: php tests/injfrd66_w5_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0; $held = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$num = static function (string $sql) use ($conn): int {
    $r = @mysqli_query($conn, $sql);
    return $r ? (int) mysqli_fetch_row($r)[0] : -1;
};
$run = static function (string $tool, string $args = '') use ($ROOT): array {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/' . $tool) . ' ' . $args . ' 2>&1', $o, $rc);
    return array($rc, implode("\n", $o));
};

echo "① إيجابيٌّ — دعاوى البلوغِ تصمد:\n";
list($rc, $txt) = $run('injfrd66_w5_reach_gate.php', '--gate');
$check($rc === 0, "بوابةُ البلوغِ تعبر (رمزٌ {$rc})");
$check(preg_match('~لم يصمد\s*0~u', $txt) === 1, 'صفرُ دعوى ساقطة');
$check(preg_match('~صمد\s*(\d+)~u', $txt, $m) === 1 && (int) $m[1] >= 110,
    'وعددُ الصامدِ ' . (isset($m[1]) ? $m[1] : '؟'));
/* ◆ والبابانِ يُفصَلان: التبويبُ يُبلَغ بأخٍ حيٍّ **أو بمِرساةٍ** — وقصرُ القياسِ
     على الإخوةِ وحدَهم حمَّر خمسةَ عشرَ صفًّا سليمًا في أوَّلِ تشغيل. */
$check(mb_strpos($txt, 'مِرساةٌ بسجلٍّ حيّ') !== false,
    'والبوابةُ تفصل بابَ المِرساةِ عن بابِ الإخوة');

echo "\n② سالبٌ — البوابةُ ترسُب بدعوى بلوغٍ مزروعةٍ لا سندَ لها:\n";
$probeRoute = 'Zz/zz_injfrd66_w5_probe.php';
@mysqli_query($conn, "DELETE FROM gov_nav_hidden_log WHERE route = '{$probeRoute}'");
$seeded = @mysqli_query($conn, "INSERT INTO gov_nav_hidden_log
        (role_id, nav_id, route, label_ar, group_before, sort_before, doc_code, reachable, hidden_at)
        VALUES (12, 0, '{$probeRoute}', 'مسبارٌ — يُزال فورَ القياس', 0, 0, 'PROBE', 'TAB_IN_PARENT', NOW())");
if (!$seeded) { $fail++; echo "   ✘ تعذّر زرعُ الدعوى: " . mysqli_error($conn) . "\n"; }
else {
    list($rc2, $txt2) = $run('injfrd66_w5_reach_gate.php', '--gate');
    @mysqli_query($conn, "DELETE FROM gov_nav_hidden_log WHERE route = '{$probeRoute}'");
    $check($rc2 === 1, "رسّبت بالمسبارِ (رمزٌ {$rc2})");
    $check(mb_strpos($txt2, 'zz_injfrd66_w5_probe.php') !== false, 'وسمَّته باسمِه');
    $check(mb_strpos($txt2, 'ولا شريطَ يُعلن مسارَه') !== false, 'وسمَّت السببَ');
    $check($num("SELECT COUNT(*) FROM gov_nav_hidden_log WHERE route='{$probeRoute}'") === 0,
        'وأُزيل المسبارُ من السجل');
}
list($rc3, ) = $run('injfrd66_w5_reach_gate.php', '--gate');
$check($rc3 === 0, "وعادت خضراءَ بعدَ الإزالة (رمزٌ {$rc3})");

echo "\n③ سالبٌ — المطابقةُ بحدِّ الاسمِ لا باحتوائه:\n";
/* «contracts_details.php» سلسلةٌ نصّيةٌ داخل «supplierscontracts_details.php» —
   ومطابقةٌ رخوةٌ تجعل سجلَّ عقودِ الموردِ يبدو كأنَّه يفتح ملفَّ عقدِ العميل. */
$hay = "\$u = 'Suppliers/supplierscontracts_details.php?id=5';";
$loose  = mb_strpos($hay, 'contracts_details.php') !== false;
$strict = (bool) preg_match('~(^|[^A-Za-z0-9_])' . preg_quote('contracts_details.php', '~') . '~', $hay);
$check($loose === true,  'المطابقةُ الرخوةُ تُخضِّر السلسلةَ الداخلة (كما توقَّعنا)');
$check($strict === false, 'والمطابقةُ بالحدِّ ترفضها — والبوابةُ تستعمل الثانية');

echo "\n④ إيجابيٌّ — المنظرانِ المشتقّان:\n";
foreach (array('v_supplier_qualification', 'v_supplier_targets_monthly') as $v) {
    $W = "WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$v}'";
    $check($num("SELECT COUNT(*) FROM information_schema.VIEWS {$W}") === 1, "`{$v}` منظرٌ لا جدول");
    $check($num("SELECT COUNT(*) FROM information_schema.VIEWS {$W} AND SECURITY_TYPE='INVOKER'") === 1,
        'وبحقوقِ المستدعي — فلا يفشل عندَ الاستعادة');
    $check($num("SELECT COUNT(*) FROM information_schema.COLUMNS {$W}
                  AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME <> 'utf8mb4_unicode_ci'") === 0,
        'وترتيبُ أعمدتِه النصّيةِ على المخطَّط');
}
$check($num("SELECT COUNT(*) FROM v_supplier_qualification
              WHERE qualification_state IS NULL OR qualification_state=''") === 0,
    'وصفرُ موردٍ بلا حكمِ تأهيل');
$check($num("SELECT COUNT(*) FROM v_supplier_targets_monthly WHERE target_month IS NULL") === 0,
    'وصفرُ شهرٍ فارغ — والحصةُ بلا نافذةٍ استُبعدت لا ابتُلعت');
/* والاستبعادُ يُقاس لا يُدَّعى: الموردُ ذو الحصةِ بلا نافذةٍ ليس في المنظر */
$noWin = $num("SELECT COUNT(DISTINCT supplier_id) FROM v_supplier_share_units
                WHERE effective_from IS NULL OR effective_to IS NULL");
$inView = $num("SELECT COUNT(*) FROM v_supplier_targets_monthly t
                 WHERE t.supplier_id IN (SELECT supplier_id FROM v_supplier_share_units
                                          WHERE effective_from IS NULL OR effective_to IS NULL)
                   AND t.supplier_id NOT IN (SELECT supplier_id FROM v_supplier_share_units
                                              WHERE effective_from IS NOT NULL AND effective_to IS NOT NULL)");
$check($inView === 0, "وموردُ الحصةِ بلا نافذةٍ ({$noWin}) خارجَ المنظرِ فعلًا");

echo "\n⑤ سالبٌ — «يشمل أشهرَ الصفر» تُثبَت بثغرةٍ مصطنعةٍ لا بعدِّ صفر:\n";
$zeroNow = $num("SELECT COUNT(*) FROM v_supplier_targets_monthly WHERE shares_active = 0");
echo "   ○ في البياناتِ الحيّةِ {$zeroNow} شهرَ صفرٍ — الحصصُ متّصلةٌ في نطاقِ كلِّ مورد.\n";
/* نُعيد بناءَ عمودِ الأشهرِ نفسِه مع **حجبِ حصصِ شهرٍ واحدٍ لموردٍ واحد**:
   لو كانت الآليةُ تُسقط الشهرَ الفارغَ لعاد الصفُّ **غائبًا**؛ ولأنَّها عمودُ
   أشهرٍ صريحٌ يعود الصفُّ **حاضرًا بصفر**. والفرقُ بينهما هو المتطلبُ كلُّه. */
$pick = @mysqli_query($conn, "SELECT supplier_id, target_month FROM v_supplier_targets_monthly
                               WHERE shares_active > 0
                            GROUP BY supplier_id, target_month
                              HAVING COUNT(*) > 0 ORDER BY supplier_id, target_month LIMIT 1 OFFSET 3");
$row = $pick ? mysqli_fetch_assoc($pick) : null;
if (!$row) { $fail++; echo "   ✘ تعذّر اختيارُ شهرٍ للثغرة\n"; }
else {
    $sid = (int) $row['supplier_id']; $mon = $row['target_month'];
    $sql = "WITH RECURSIVE span AS (
              SELECT company_id, supplier_id,
                     DATE_FORMAT(MIN(effective_from), '%Y-%m-01') m_first,
                     DATE_FORMAT(MAX(effective_to),   '%Y-%m-01') m_last
                FROM v_supplier_share_units
               WHERE supplier_id IS NOT NULL
                 AND effective_from IS NOT NULL AND effective_to IS NOT NULL
               GROUP BY company_id, supplier_id
            ), months AS (
              SELECT company_id, supplier_id, CAST(m_first AS DATE) mon, CAST(m_last AS DATE) m_last
                FROM span
              UNION ALL
              SELECT company_id, supplier_id, DATE_ADD(mon, INTERVAL 1 MONTH), m_last
                FROM months WHERE mon < m_last
            )
            SELECT COUNT(*) FROM (
              SELECT m.supplier_id, DATE_FORMAT(m.mon,'%Y-%m') mm, COUNT(u.supplier_container_id) act
                FROM months m
                LEFT JOIN v_supplier_share_units u
                       ON u.supplier_id = m.supplier_id AND u.company_id = m.company_id
                      AND u.effective_from IS NOT NULL AND u.effective_to IS NOT NULL
                      AND u.effective_from <= LAST_DAY(m.mon) AND u.effective_to >= m.mon
                      /* ← الثغرةُ المصطنعة: تُحجَب حصصُ هذا الموردِ في هذا الشهرِ وحدَه */
                      AND NOT (m.supplier_id = {$sid} AND DATE_FORMAT(m.mon,'%Y-%m') = '{$mon}')
               GROUP BY m.supplier_id, m.mon
            ) x WHERE x.supplier_id = {$sid} AND x.mm = '{$mon}' AND x.act = 0";
    $got = $num($sql);
    $check($got === 1, "بثغرةٍ مصطنعةٍ (مورد {$sid} · {$mon}) يعود الشهرُ **حاضرًا بصفرٍ** لا غائبًا");
    $check($num("SELECT COUNT(*) FROM v_supplier_targets_monthly
                  WHERE supplier_id={$sid} AND target_month='{$mon}' AND shares_active=0") === 0,
        'وبياناتُ الإنتاجِ لم تُمَسّ — الثغرةُ في الاستعلامِ لا في القاعدة');
}

echo "\n⑥ سالبٌ — SUP-13 لا يُقاس بالطوابعِ الزمنية:\n";
$swaps = $num("SELECT COUNT(*) FROM container_swaps");
$byTime = $num("SELECT COUNT(*) FROM container_swaps s
                  JOIN op_containers c ON c.id = s.to_container_id
                 WHERE c.created_at > s.created_at");
$byLine = $num("SELECT COUNT(*) FROM container_swaps s
             LEFT JOIN op_containers c ON c.id = s.to_container_id
                 WHERE c.id IS NULL OR c.contract_id IS NULL OR c.contract_id = 0");
$check($byTime === $swaps && $swaps > 0,
    "المقياسُ الزمنيُّ يعطي {$byTime} من {$swaps} «خرقًا» — رقمٌ مقلوبٌ سببُه بذرةٌ دفعةً واحدة");
$check($byLine === 0, "والمقياسُ بالنَّسَبِ يعطي {$byLine} — وهو الحكم");
$check($byTime !== $byLine, 'والمقياسانِ يفترقان — فاختيارُ أحدِهما ليس تفصيلًا');

echo "\n⑦ محجوزٌ بسببٍ مكتوب:\n";
list($rc4, $txt4) = $run('injfrd66_w5_gate.php');
$check(mb_strpos($txt4, 'لموردٍ ناقصِ التأهيل') !== false,
    'SUP-03 أحمرُ مقيسٌ — 20 عقدًا من 20');
$check(mb_strpos($txt4, 'نسختان بأبجديّتَين') !== false,
    'SUP-29 أحمرُ مقيسٌ — العملةُ بأبجديّتَين');
$check(preg_match('~أحمر\s*2~u', $txt4) === 1, 'وأحمرانِ لا ثالثَ لهما — فأيُّ خرقٍ جديدٍ يظهر');
$held += 2;
echo "   ⏸ حارسُ التأهيلِ محجوزٌ: إنفاذُه يُخالف عقودَ الإدارةِ العشرينَ كلَّها.\n";
echo "   ⏸ وتوحيدُ أبجديّةِ العملةِ محجوزٌ: يمسُّ كلَّ قارئٍ للعملةِ نصًّا.\n";

printf("\n%s  ناجح %d · راسب %d · محجوز %d\n",
    $fail === 0 ? '✔ الموجة ⑤' : '✘ الموجة ⑤', $pass, $fail, $held);
exit($fail === 0 ? 0 : 1);
