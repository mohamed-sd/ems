<?php
/**
 * tools/rpr02_platform_register.php — `RPR-02` #١٣ · تسجيلُ أسطحِ المنصّةِ بقدرتِها
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي يرفعه** — `RPR-02` **#١٣** و`RPR-03` **#٨** سؤالٌ واحدٌ
 *   بقارئَين: **٣٠ سطحًا** حكمُ ملكيّتِها `PLATFORM_SHARED` و**سجلُّ القدراتِ فيه
 *   صفٌّ واحد** ⇒ *«لا واحدةَ منها مسجَّلةٌ بمعرِّفِها وقاعدةِ ظهورِها»*.
 *
 * ◆ **وما كان ناقصًا ليس البياناتِ بل الربط** — والمقيس: `visibility_class`
 *   و`visibility_rule` و`permission_policy` و`guard_kind` و`owner_role` مملوءةٌ
 *   في **٣٠ من ٣٠**، و`owner_code` في **١٨**. ⇒ **فالمقياسُ يربط ما هو مقيسٌ
 *   ولا يخترع بيانًا**.
 *
 * ◆ **وثلاثُ قواعدَ للربط**:
 *   **P1 · `DECLARED_SCOPE_OWNER`** — رمزُ المالكِ **نطاقٌ مُعلَنٌ** من الواحدِ
 *        والعشرين. ⇒ السطحُ **ليس بلا مالك**، و`PLATFORM_SHARED` فيه **صفةُ
 *        ظهورٍ عابرٍ للأدوار لا فراغُ ملكيّة**. ⛔ **والخلطُ بينهما أصلُ العطب**:
 *        `main/profile.php` مملوكٌ لـ`WS-MY` **ويظهر لكلِّ دورٍ** — وذلك ظهورٌ
 *        مقصودٌ لا ملكيّةٌ مفقودة.
 *   **P2 · `CAPABILITY_BOUND`** — بلا مالكٍ، ومسارُه أو وسمُه يطابق **قدرةً
 *        واحدةً لا غير** من قدراتِ `AMD-01` §٤·٧ **الثمانِ المسمّاةِ في الأمر**
 *        — ⛔ **ولا تُخترع قدرةٌ تاسعة**.
 *   **P3 · `UNBOUND_DECLARED`** — بلا مالكٍ ولا قدرةٍ مميِّزة، **أو قدرتان
 *        فأكثرُ** ⇒ **يُعلَن مفتوحًا بمرشَّحيه**. ⛔ **ولا يُلحق بأوّلِ مصادفة.**
 *
 * ⛔ **والتسجيلُ ليس اعتمادًا** — و#١٣ يشترط تبريرًا **معتمَدًا**. فكلُّ صفٍّ
 *   يولد `AWAITING_OWNER`، **والمقياسُ لا ينخفض بالتسجيل**: ما ينخفض هو
 *   **مجهولُ السبب**. ⇒ فالباقي يصير **فعلَ اعتمادٍ واحدًا لا عملَ تحليل**.
 *   ⛔ **ومن يملك وسمَ الاعتمادِ بلا مرجعٍ يملك تصفيرَ #١٣ بجملةٍ واحدة** —
 *   وقاعدةٌ صلبةٌ في القاعدةِ نفسِها تردُّه.
 *
 * التشغيل:
 *   php tools/rpr02_platform_register.php [--apply] [--md] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① قدراتُ `AMD-01` §٤·٧ الثمان — ⛔ ولا تاسعةَ تُخترع ════════════════ */
$CAP = array(
    'APPROVAL_ENGINE'       => array('محرِّكُ الاعتماد',
        array('approval','approvals'), array('اعتماد','موافق')),
    'REQUEST_ROUTING'       => array('توجيهُ الطلبات',
        array('request','requests','routing'), array('طلب','طلبات','توجيه')),
    'ENTERPRISE_SEARCH'     => array('البحثُ المؤسسيّ',
        array('search','glossary'), array('بحث','قاموس')),
    'SCREEN_REGISTRY'       => array('سجلُّ الشاشات',
        array('screen','portal_elements','soon'), array('شاشة','مكو')),
    'PERMISSION_CAPABILITY' => array('قدرةُ الصلاحيات',
        array('visibility','users','project_users','assistants','capacities'),
        array('صلاحي','ظهور','يرى','مستخدم','معاون','صفات')),
    'SECURITY_CAPABILITY'   => array('قدرةُ الأمان',
        array('password','guard_denials','audit'), array('كلمة المرور','منع','تدقيق','مراجعة')),
    'EVENT_INFRA'           => array('بنيةُ الأحداث',
        array('event','notification'), array('حدث','تنبيه','إشعار')),
    'SHARED_DOCS_NOTIF'     => array('المستنداتُ والإشعاراتُ المشتركة',
        array('report','reports','certificate'), array('تقرير','تقارير','شهادة')),
);
/* النطاقاتُ الواحدُ والعشرون — `AMD-01` §٤·٧ */
$SCOPES = array('EX-CEO','EX-DVP','WS-MY','IAF');
for ($i = 1; $i <= 17; $i++) { $SCOPES[] = sprintf('DEP-%02d', $i); }

function pr_match($route, $label, $CAP)
{
    $rt = mb_strtolower((string) $route, 'UTF-8');
    $hit = array();
    foreach ($CAP as $code => $d) {
        $m = false;
        foreach ($d[1] as $w) { if (strpos($rt, $w) !== false) { $m = true; } }
        foreach ($d[2] as $w) { if (mb_strpos((string) $label, $w) !== false) { $m = true; } }
        if ($m) { $hit[] = $code; }
    }
    return $hit;
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    $h = pr_match('main/global_search.php', 'الرئيسية', $CAP);
    if ($h !== array('ENTERPRISE_SEARCH')) { echo "  X البحثُ لم يُربَط وحدَه\n"; $fail++; }
    $h = pr_match('Portal/notifications.php', 'التنبيهات', $CAP);
    if (!in_array('EVENT_INFRA', $h, true)) { echo "  X بنيةُ الأحداثِ لم تُربَط\n"; $fail++; }
    /* ⛔ **والالتباسُ يُرصد لا يُخفى**: سطحٌ يطابق قدرتَين يبقى مفتوحًا */
    $h = pr_match('Reports/guard_denials_report.php', 'تقرير المنع', $CAP);
    if (count($h) < 2) { echo "  X الالتباسُ لم يُرصَد\n"; $fail++; }
    /* **الكاسر**: مسارٌ ووسمٌ لا يطابقان شيئًا — ولو طابقا لَمرَّ الربطُ زورًا */
    if (pr_match('zzq/unique_probe.php', 'مفردةٌ فريدةٌ للفحص', $CAP)) {
        echo "  X المفردةُ الفريدةُ رُبطت بقدرة\n"; $fail++;
    }
    if (count($CAP) !== 8) { echo "  X القدراتُ ليست ثمانيًا — قدرةٌ تاسعةٌ اخترعت\n"; $fail++; }
    if (count($SCOPES) !== 21) { echo "  X النطاقاتُ ليست واحدًا وعشرين\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والربطُ يميّز والالتباسُ يُرصد ولا يُخفى\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ الربط ════════════════════════════════════════════════════════════ */
$rows = array(); $stat = array('P1' => 0, 'P2' => 0, 'P3' => 0);
$capUse = array();
$r = $conn->query("SELECT screen_id, canonical_label_ar, route, owner_code, owner_role,
                          visibility_class, visibility_rule, permission_policy, guard_kind
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict = 'PLATFORM_SHARED'
                    ORDER BY screen_id");
while ($x = $r->fetch_assoc()) {
    $own = trim((string) $x['owner_code']);
    $hit = pr_match($x['route'], $x['canonical_label_ar'], $CAP);
    $base = 'ظهورُه `' . $x['visibility_class'] . '` بقاعدةِ `' . $x['visibility_rule']
          . '` · سياسةُ صلاحيّتِه `' . $x['permission_policy'] . '` · حارسُه `' . $x['guard_kind']
          . '` · دورُ مالكِه «' . $x['owner_role'] . '» — **كلُّها مقيسةٌ لا مُعلَنة**';
    if ($own !== '' && in_array($own, $SCOPES, true)) {
        $rule = 'P1_DECLARED_SCOPE_OWNER'; $scope = $own; $cap = '';
        $wit = 'P1 · مالكُه `' . $own . '` **نطاقٌ مُعلَنٌ** من الواحدِ والعشرين ⇒ '
             . '**ليس بلا مالك**، و`PLATFORM_SHARED` فيه **صفةُ ظهورٍ عابرٍ للأدوارِ لا فراغُ ملكيّة** · '
             . $base . ' · لقطة ' . $sid;
        $stat['P1']++;
    } elseif ($own === '' && count($hit) === 1) {
        $rule = 'P2_CAPABILITY_BOUND'; $scope = ''; $cap = $hit[0];
        $capUse[$cap][] = $x['screen_id'];
        $wit = 'P2 · بلا رمزِ مالكٍ، ومسارُه `' . $x['route'] . '` أو وسمُه «' . $x['canonical_label_ar']
             . '» يطابق **قدرةً واحدةً لا غير**: `' . $cap . '` («' . $CAP[$cap][0] . '») '
             . 'من قدراتِ `AMD-01` §٤·٧ الثمان · ' . $base . ' · لقطة ' . $sid;
        $stat['P2']++;
    } else {
        $rule = 'P3_UNBOUND_DECLARED'; $scope = ''; $cap = '';
        $why = ($own !== '')
            ? '**رمزُ مالكِه `' . $own . '` ليس نطاقًا مُعلَنًا** من الواحدِ والعشرين'
            : (count($hit) > 1
               ? '**يطابق ' . count($hit) . ' قدراتٍ** (' . implode(' · ', $hit) . ') ⇒ **المطابقةُ لا تميّز**'
               : '**لا رمزَ مالكٍ ولا قدرةَ يطابقها** مسارُه ولا وسمُه');
        $wit = 'P3 · ' . $why . ' ⇒ **يُعلَن مفتوحًا ولا يُلحق بأوّلِ مصادفة** · ' . $base . ' · لقطة ' . $sid;
        $stat['P3']++;
    }
    $rows[] = array($x['screen_id'], $x['canonical_label_ar'], $x['route'], $rule, $scope, $cap,
                    $x['visibility_class'], $x['visibility_rule'], $x['permission_policy'],
                    $x['guard_kind'], $x['owner_role'], $wit);
}

/* ═══ ⑤ العرض ════════════════════════════════════════════════════════════ */
$N = count($rows);
echo "\n═══ `RPR-02` #١٣ — تسجيلُ أسطحِ المنصّةِ بقدرتِها أو نطاقِها ═══\n";
printf("  اللقطة: %s · أسطحٌ حكمُ ملكيّتِها `PLATFORM_SHARED`: **%d**\n\n", $sid, $N);
echo "  ── القواعدُ الثلاث ──\n";
printf("     P1 `DECLARED_SCOPE_OWNER` %2d — مالكُه نطاقٌ مُعلَنٌ ⇒ **ظهورٌ عابرٌ لا فراغُ ملكيّة**\n", $stat['P1']);
printf("     P2 `CAPABILITY_BOUND`     %2d — قدرةٌ واحدةٌ من ثمانِ `AMD-01` §٤·٧\n", $stat['P2']);
printf("     P3 `UNBOUND_DECLARED`     %2d — ⛔ **يُعلَن مفتوحًا بمرشَّحيه**\n", $stat['P3']);
printf("\n  ⇒ **مسجَّلٌ بمعرِّفِه وقاعدةِ ظهورِه: %d من %d** · وبلا ربطٍ %d\n",
       $stat['P1'] + $stat['P2'], $N, $stat['P3']);
echo "  ⛔ **والتسجيلُ ليس اعتمادًا** — كلُّها `AWAITING_OWNER`، و#١٣ يشترط **معتمَدًا**.\n";
echo "     ⇒ فالباقي **فعلُ اعتمادٍ واحدٌ لا عملُ تحليل**.\n";

if ($capUse) {
    echo "\n  ── القدراتُ المستعمَلة ──\n";
    foreach ($capUse as $k => $v) { printf("   %-24s %s\n", $k, implode(' · ', $v)); }
}
echo "\n  ── الأسطحُ ──\n";
foreach ($rows as $x) {
    printf("   %-10s %-30s %-26s %s\n", $x[0], mb_substr($x[1], 0, 28),
           substr($x[3], 0, 24), $x[4] !== '' ? $x[4] : ($x[5] !== '' ? $x[5] : '⛔'));
}

/* ═══ ⑥ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_platform_surface'");
    if (!$has || !$has->num_rows) {
        exit("⛔ **`repair01_platform_surface` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_07_rpr02_platform_surface.php\n");
    }
    $conn->query("DELETE FROM repair01_platform_surface");
    $n = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("INSERT INTO repair01_platform_surface
              (screen_id,label_ar,route,bind_rule,scope_code,capability_code,
               visibility_class,visibility_rule,permission_policy,guard_kind,owner_role,
               approval_state,witness,snapshot_id,measured_at)
            VALUES ('" . $e($x[0]) . "','" . $e(mb_substr($x[1], 0, 190)) . "','" . $e(mb_substr($x[2], 0, 190))
             . "','" . $e($x[3]) . "','" . $e($x[4]) . "','" . $e($x[5]) . "','" . $e($x[6]) . "','"
             . $e($x[7]) . "','" . $e(mb_substr($x[8], 0, 80)) . "','" . $e($x[9]) . "','"
             . $e(mb_substr($x[10], 0, 80)) . "','AWAITING_OWNER','" . $e(mb_substr($x[11], 0, 600))
             . "','" . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$x[0]}: {$conn->error}\n"); }
        $n++;
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_surface WHERE witness = ''")->fetch_row()[0];
    $appr = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_surface
                                 WHERE approval_state = 'APPROVED'")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** سطحًا · صفٌّ بلا شاهدٍ %d · **معتمَدٌ %d** (‏والتسجيلُ ليس اعتمادًا)\n",
           $n, $bad, $appr);
}

if ($MD) {
    $o  = "# `RPR-02` #١٣ — تسجيلُ أسطحِ المنصّةِ بقدرتِها أو نطاقِها\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## ما كان ناقصًا ليس البياناتِ بل الربط\n\n";
    $o .= "الثلاثون تحمل **كلُّها** قاعدةَ ظهورٍ وصنفَ ظهورٍ وسياسةَ صلاحيّةٍ وصنفَ حارسٍ ودورَ\n";
    $o .= "مالكٍ **مقيسةً** (٣٠/٣٠)، و**١٨** منها تحمل رمزَ مالكٍ أيضًا. ⇒ فالمقياسُ **يربط ما\n";
    $o .= "هو مقيسٌ ولا يخترع بيانًا**.\n\n";
    $o .= "## القواعدُ الثلاث\n\n| القاعدة | أسطح | الحكم |\n|---|---:|---|\n";
    $o .= "| `P1` مالكُه نطاقٌ مُعلَن | **" . $stat['P1'] . "** | `PLATFORM_SHARED` فيه **صفةُ ظهورٍ عابرٍ للأدوارِ لا فراغُ ملكيّة** |\n";
    $o .= "| `P2` قدرةٌ واحدةٌ من الثمان | **" . $stat['P2'] . "** | من قدراتِ `AMD-01` §٤·٧ — ⛔ **ولا تاسعةَ تُخترع** |\n";
    $o .= "| `P3` بلا ربطٍ مميِّز | **" . $stat['P3'] . "** | ⛔ **يُعلَن مفتوحًا بمرشَّحيه** |\n\n";
    $o .= "⇒ **مسجَّلٌ بمعرِّفِه وقاعدةِ ظهورِه: " . ($stat['P1'] + $stat['P2']) . " من " . $N . "**.\n\n";
    $o .= "## ⛔ والتسجيلُ ليس اعتمادًا\n\n";
    $o .= "و#١٣ يشترط تبريرًا **معتمَدًا**. فكلُّ صفٍّ يولد `AWAITING_OWNER`، **والمقياسُ لا\n";
    $o .= "ينخفض بالتسجيل**: ما ينخفض هو **مجهولُ السبب**. ⇒ فالباقي **فعلُ اعتمادٍ واحدٌ لا\n";
    $o .= "عملُ تحليل**. وقاعدةٌ صلبةٌ في القاعدةِ نفسِها: `APPROVED` **يوجب مرجعَ قرارِ مالك**.\n\n";
    $o .= "## الأسطحُ بشواهدِها\n\n";
    $o .= "| السطح | الوسم | القاعدة | النطاق/القدرة |\n|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= "| `" . $x[0] . "` | " . $x[1] . " | `" . $x[3] . "` | "
            . ($x[4] !== '' ? '`' . $x[4] . '`' : ($x[5] !== '' ? '`' . $x[5] . '`' : '⛔ مفتوح')) . " |\n";
    }
    $o .= "\n### الشواهدُ كاملةً\n\n";
    foreach ($rows as $x) { $o .= "- **`" . $x[0] . "`** — " . $x[11] . "\n"; }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S13_PLATFORM.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S13_PLATFORM.md\n";
}
