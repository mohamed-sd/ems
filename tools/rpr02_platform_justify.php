<?php
/**
 * tools/rpr02_platform_justify.php — `RPR-02` #١٣ · تبريرُ `PLATFORM` بأربعتِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-02` §٥·٤: *«`PLATFORM` ملكيّةٌ مشروعةٌ في دستورِنا
 *   **لا خطأٌ يُصفَّر** — ولا تُقبل بلا تبرير … تُغلَق **بأربعةٍ مجتمعة**:
 *   ① تخدم **ثلاثَ إداراتٍ فأكثر** خدمةً **فعليّةً** لا محتملة · ② **لا تملك
 *   حقيقةَ أعمال** · ③ لها **مالكٌ تقنيٌّ مسمًّى شخصًا** لا وحدةً مبهمة ·
 *   ④ **مسجَّلةٌ في سجلِّ المنصّة** بمعرِّفِها وقاعدةِ ظهورِها. ⛔ **وما لم
 *   يستوفِ الأربعةَ يعود إلى إدارتِه من السبعَ عشرة**»*.
 *
 * ◆ **وهذا المقياسُ يُنفِّذ الجملةَ الأخيرةَ** — وهي التي لم تُنفَّذ:
 *   `rpr02_platform_register.php` سجَّل (‏المعيار ④) **وأحسن**، ثمّ وقف عند
 *   «فعلِ اعتمادٍ» — **والأمرُ لا يُحيل الاعتمادَ إلى مالكٍ بل إلى الأربعة**،
 *   ⛔ **ورفعُ خامسٍ إلى المالكِ ممنوعٌ بنصِّ §٥ المحظور ⑧**. ⇒ فمرجعُ
 *   الاعتمادِ **`RPR-02 §٥·٤` نفسُه**: قاعدةٌ مكتوبةٌ سابقةٌ للقياسِ لا وسمٌ
 *   يُمنح بجملة.
 *
 * ◆ **ثلاثةُ أحكامٍ للسطح — ⛔ ولا رابعَ يُخترع**:
 *   **J1 · `PLATFORM_JUSTIFIED`** — استوفى الأربعةَ ⇒ `PLATFORM_SHARED` حكمٌ
 *        مبرَّرٌ معتمَدٌ بمرجعِه.
 *   **J2 · `RETURN_TO_SCOPE`** — أخلَّ بواحدٍ **وله نطاقٌ مُعلَنٌ** من الواحدِ
 *        والعشرين ⇒ **يعود إليه بنصِّ §٥·٤**. ⛔ **ولا يضيع ظهورُه العابر**:
 *        الظهورُ مسجَّلٌ في `visibility_class`/`visibility_rule` — **وهو موضعُه**،
 *        وحملُ عمودِ الملكيّةِ معنى الظهورِ هو أصلُ الخلطِ نفسِه.
 *   **J3 · `NO_SCOPE_TO_RETURN`** — أخلَّ بواحدٍ **ولا نطاقَ مُعلَنَ له** ⇒
 *        ⛔ **يُعلَن بحاجزِه بالاسم ولا يُلفَّق له مالك**.
 *
 * ⛔ **والمعيارُ ③ غيرُ مقيسٍ لأنَّ بيانَه غيرُ موجود** — لا في
 *   `repair01_platform_capabilities.tech_owner` (‏فارغٌ في الصفِّ الوحيد) ولا في
 *   أيِّ جدولٍ آخر. **وتسميةُ شخصٍ من عندنا تلفيقٌ يمنعه §٥ المحظور ④.**
 *   ⇒ يُكتب `NEEDS_GOVERNING_SOURCE` **صراحةً**، ويُحسب **مُخِلًّا** — فيؤول
 *   السطحُ إلى `J2` أو `J3`. ⛔ **ولا يُعدُّ مستوفًى بالسكوت.**
 *
 * ◆ **والمعيارُ ① مقيسٌ من المُصيَّرِ لا من النيّة**: عددُ الأدوارِ الحيّةِ التي
 *   يظهر لها مسارُ السطحِ في `nav_items` النشطة. **فالخدمةُ الفعليّةُ ظهورٌ
 *   قائمٌ لا نيّةُ تصميم.**
 *
 * التشغيل:
 *   php tools/rpr02_platform_justify.php [--apply] [--md] [--selftest]
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

/* ═══ ① الأحكامُ مفصولةً — كي تُختبر وحدَها ═══════════════════════════════ */
/** المعيارُ ①: خدمةٌ فعليّةٌ لثلاثِ جهاتٍ فأكثرَ — من المُصيَّرِ لا من النيّة. */
function pj_serves_three($rolesRendered)
{
    return ((int) $rolesRendered) >= 3;
}
/** المعيارُ ②: لا يملك حقيقةَ أعمالٍ — والملكيّةُ حبّةُ سجلٍّ في مصدرٍ خاصّ. */
function pj_owns_no_fact($factScope, $cardinality)
{
    return !((string) $factScope === 'OWN_FACT'
             && in_array((string) $cardinality, array('ROW', 'LINE'), true));
}
/** حكمُ السطحِ من الأربعةِ ومن وجودِ نطاقٍ مُعلَن. */
function pj_verdict($c1, $c2, $c3, $c4, $hasScope)
{
    if ($c1 && $c2 && $c3 && $c4) { return 'PLATFORM_JUSTIFIED'; }
    return $hasScope ? 'RETURN_TO_SCOPE' : 'NO_SCOPE_TO_RETURN';
}

/* ═══ ② الاختبارُ السالبُ — يُصيب الطرفَين ولا يمرُّ بمفردةٍ فريدة ═══════ */
if ($SELF) {
    $fail = 0;
    if (!pj_serves_three(3)) { echo "  X الثلاثةُ لم تُعَدَّ خدمةً\n"; $fail++; }
    if (!pj_serves_three(9)) { echo "  X ما فوقَ الثلاثةِ رُدَّ\n"; $fail++; }
    /* ⛔ **والاثنتان ليستا ثلاثًا** — والحدُّ منصوصٌ لا مُقرَّب */
    if (pj_serves_three(2)) { echo "  X الاثنتان عُدَّتا ثلاثًا\n"; $fail++; }
    if (pj_serves_three(0)) { echo "  X الصفرُ عُدَّ خدمةً\n"; $fail++; }
    if (!pj_owns_no_fact('SHARED_KIT', 'ROW'))  { echo "  X الكِيتُ المشتركُ عُدَّ ملكيّةً\n"; $fail++; }
    if (!pj_owns_no_fact('OWN_FACT', 'LIST'))   { echo "  X القراءةُ عُدَّت ملكيّةً\n"; $fail++; }
    /* **الكاسر**: خاصٌّ بحبّةِ سجلٍّ ⇒ **يملك** فلا يمرُّ */
    if (pj_owns_no_fact('OWN_FACT', 'ROW'))     { echo "  X المالكُ الحقيقيُّ مرَّ\n"; $fail++; }
    if (pj_owns_no_fact('OWN_FACT', 'LINE'))    { echo "  X مالكُ السطرِ مرَّ\n"; $fail++; }
    /* الحكمُ: الأربعةُ معًا لا ثلاثةٌ منها */
    if (pj_verdict(true, true, true, true, false) !== 'PLATFORM_JUSTIFIED') {
        echo "  X المستوفي لم يُبرَّر\n"; $fail++;
    }
    if (pj_verdict(true, true, false, true, true) !== 'RETURN_TO_SCOPE') {
        echo "  X المُخِلُّ بواحدٍ لم يعُدْ إلى نطاقِه\n"; $fail++;
    }
    if (pj_verdict(true, true, false, true, false) !== 'NO_SCOPE_TO_RETURN') {
        echo "  X المُخِلُّ بلا نطاقٍ لُفِّق له مالك\n"; $fail++;
    }
    /* ⛔ **والثلاثةُ من أربعةٍ ليست استيفاءً** */
    if (pj_verdict(true, true, true, false, false) === 'PLATFORM_JUSTIFIED') {
        echo "  X ثلاثةٌ من أربعةٍ عُدَّت استيفاءً\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والأربعةُ معًا لا بعضُها، والاثنتان ليستا ثلاثًا\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ المعيارُ ③ — مصدرُه يُسأل ولا يُفترَض ═════════════════════════════ */
$techOwners = array();
$q = @$conn->query("SELECT capability_code, tech_owner FROM repair01_platform_capabilities
                     WHERE tech_owner <> ''");
while ($q && $z = $q->fetch_assoc()) { $techOwners[$z['capability_code']] = $z['tech_owner']; }

/* النطاقاتُ الواحدُ والعشرون — ⛔ ورمزٌ خارجَها ليس نطاقًا مُعلَنًا */
$scopes = array();
$q = $conn->query("SELECT canonical_code FROM repair01_departments");
while ($q && $z = $q->fetch_row()) { $scopes[$z[0]] = 1; }

/* الظهورُ المُصيَّرُ لكلِّ مسار — أدوارٌ حيّةٌ لا نيّةُ تصميم */
$byRoute = array();
$q = $conn->query("SELECT LOWER(TRIM(BOTH '/' FROM SUBSTRING_INDEX(route, '?', 1))) rt,
                          COUNT(DISTINCT role_id) n
                     FROM nav_items WHERE active = 1 AND route <> '' GROUP BY rt");
while ($q && $z = $q->fetch_row()) { $byRoute[$z[0]] = (int) $z[1]; }

/* سجلُّ المنصّةِ — المعيارُ ④ */
$reg = array();
$q = @$conn->query("SELECT screen_id, bind_rule, capability_code, visibility_rule
                      FROM repair01_platform_surface");
while ($q && $z = $q->fetch_assoc()) { $reg[$z['screen_id']] = $z; }

/* ═══ ⑤ المدى — أسطحُ `PLATFORM_SHARED` الحيّة ═══════════════════════════ */
$rows = array();
$q = $conn->query("SELECT screen_id, canonical_label_ar, route, owner_code,
                          grain_fact_scope, grain_cardinality, visibility_class, visibility_rule
                     FROM repair01_screen_registry
                    WHERE on_disk = 1 AND ownership_verdict = 'PLATFORM_SHARED'
                    ORDER BY screen_id");
while ($z = $q->fetch_assoc()) { $rows[] = $z; }

$stat = array('J1' => 0, 'J2' => 0, 'J3' => 0);
$c1n = 0; $c2n = 0; $c3n = 0; $c4n = 0;
$plan = array();
foreach ($rows as $x) {
    $rt = strtolower(trim(preg_replace('~[?#].*$~', '', (string) $x['route']), '/'));
    $roles = isset($byRoute[$rt]) ? $byRoute[$rt] : 0;
    $c1 = pj_serves_three($roles);
    $c2 = pj_owns_no_fact($x['grain_fact_scope'], $x['grain_cardinality']);
    $rg = isset($reg[$x['screen_id']]) ? $reg[$x['screen_id']] : null;
    $cap = $rg ? (string) $rg['capability_code'] : '';
    $c3 = ($cap !== '' && isset($techOwners[$cap]) && trim($techOwners[$cap]) !== '');
    $c4 = ($rg !== null && $rg['bind_rule'] !== 'P3_UNBOUND_DECLARED'
           && trim((string) $rg['visibility_rule']) !== '');
    $hasScope = (trim((string) $x['owner_code']) !== '' && isset($scopes[$x['owner_code']]));
    $v = pj_verdict($c1, $c2, $c3, $c4, $hasScope);
    if ($c1) { $c1n++; } if ($c2) { $c2n++; } if ($c3) { $c3n++; } if ($c4) { $c4n++; }
    $stat[$v === 'PLATFORM_JUSTIFIED' ? 'J1' : ($v === 'RETURN_TO_SCOPE' ? 'J2' : 'J3')]++;

    $miss = array();
    if (!$c1) { $miss[] = '① خدمةٌ فعليّةٌ لثلاثٍ (‏المُصيَّرُ ' . $roles . ' دورًا)'; }
    if (!$c2) { $miss[] = '② لا يملك حقيقةَ أعمالٍ (‏يملك `' . $x['grain_fact_scope'] . '`/`'
                        . $x['grain_cardinality'] . '`)'; }
    if (!$c3) { $miss[] = '③ مالكٌ تقنيٌّ مسمًّى شخصًا (`NEEDS_GOVERNING_SOURCE` — لا بيانَ له في أيِّ جدول)'; }
    if (!$c4) { $miss[] = '④ مسجَّلٌ بمعرِّفِه وقاعدةِ ظهورِه'; }

    $wit = ($v === 'PLATFORM_JUSTIFIED')
        ? 'J1 · استوفى **الأربعةَ مجتمعةً** (§٥·٤): ظهورٌ مُصيَّرٌ لـ' . $roles . ' دورًا · لا يملك '
          . 'حقيقةَ أعمالٍ · مالكٌ تقنيٌّ مسمًّى · مسجَّلٌ بقاعدةِ ظهورِه ⇒ **تبريرٌ معتمَدٌ '
          . 'بمرجعِ `RPR-02 §٥·٤`** لا بوسمٍ يُمنح · لقطة ' . $sid
        : (($v === 'RETURN_TO_SCOPE')
           ? 'J2 · **أخلَّ بـ' . count($miss) . '** من أربعةِ §٥·٤: ' . implode(' · ', $miss)
             . ' ⇒ **يعود إلى نطاقِه المُعلَن `' . $x['owner_code'] . '`** بنصِّ §٥·٤ '
             . '(«وما لم يستوفِ الأربعةَ يعود إلى إدارتِه»). ◆ **وظهورُه العابرُ لا يضيع**: '
             . 'مسجَّلٌ في `visibility_class=' . ($x['visibility_class'] === '' ? '—' : $x['visibility_class'])
             . '` — وهو موضعُه · لقطة ' . $sid
           : 'J3 · **أخلَّ بـ' . count($miss) . '** من أربعةِ §٥·٤: ' . implode(' · ', $miss)
             . ' ⇒ ⛔ **ولا نطاقَ مُعلَنًا يعود إليه** (`owner_code` فارغٌ أو خارجَ الواحدِ '
             . 'والعشرين) — **فيُعلَن بحاجزِه بالاسمِ ولا يُلفَّق له مالك** · لقطة ' . $sid);

    $plan[] = array('id' => $x['screen_id'], 'label' => $x['canonical_label_ar'], 'v' => $v,
                    'scope' => $x['owner_code'], 'roles' => $roles, 'wit' => $wit,
                    'c' => ($c1 ? '1' : '·') . ($c2 ? '2' : '·') . ($c3 ? '3' : '·') . ($c4 ? '4' : '·'));
}

/* ═══ ⑥ العرض ════════════════════════════════════════════════════════════ */
$N = count($rows);
echo "\n═══ `RPR-02` #١٣ — تبريرُ `PLATFORM` بأربعتِه (§٥·٤) ═══\n";
printf("  اللقطة: %s · أسطحُ `PLATFORM_SHARED` الحيّة: **%d**\n\n", $sid, $N);
echo "  ── المعاييرُ الأربعةُ · كم استوفاه ──\n";
printf("     ① خدمةٌ فعليّةٌ لثلاثِ جهاتٍ فأكثر   **%2d** من %d\n", $c1n, $N);
printf("     ② لا يملك حقيقةَ أعمال              **%2d** من %d\n", $c2n, $N);
printf("     ③ مالكٌ تقنيٌّ مسمًّى شخصًا           **%2d** من %d — `NEEDS_GOVERNING_SOURCE`\n", $c3n, $N);
printf("     ④ مسجَّلٌ بمعرِّفِه وقاعدةِ ظهورِه    **%2d** من %d\n", $c4n, $N);
echo "\n  ── الحكمُ ──\n";
printf("     J1 `PLATFORM_JUSTIFIED`   %2d — استوفى الأربعةَ ⇒ **تبريرٌ معتمَدٌ بمرجعِ §٥·٤**\n", $stat['J1']);
printf("     J2 `RETURN_TO_SCOPE`      %2d — أخلَّ بواحدٍ وله نطاقٌ ⇒ **يعود إليه بنصِّ §٥·٤**\n", $stat['J2']);
printf("     J3 `NO_SCOPE_TO_RETURN`   %2d — أخلَّ بواحدٍ ولا نطاقَ ⇒ ⛔ **يُعلَن ولا يُلفَّق**\n", $stat['J3']);
printf("\n  ⇒ المقياسُ **#١٣ %d ⇒ %d** (‏الباقي `PLATFORM_SHARED` بلا تبرير)\n",
       $N, $stat['J3']);
echo "  ◆ **والمعيارُ ③ حاجزُ بيانٍ لا حاجزُ تحليل**: `tech_owner` فارغٌ في مصدرِه\n";
echo "    الوحيدِ — ⛔ **وتسميةُ شخصٍ من عندنا تلفيقٌ يمنعه §٥ المحظور ④**\n";

echo "\n  ── الأسطحُ ──\n";
foreach ($plan as $x) {
    printf("   %-10s %-32s %-20s %-6s %s\n", $x['id'], mb_substr($x['label'], 0, 30),
           $x['v'], $x['c'], ($x['scope'] === '' ? '—' : $x['scope']));
}

/* ═══ ⑦ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_platform_justification'");
    if (!$has || !$has->num_rows) {
        exit("\n⛔ **`repair01_platform_justification` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_09_rpr02_platform_justify.php\n");
    }
    $conn->query("DELETE FROM repair01_platform_justification");
    $n = 0; $moved = 0;
    foreach ($plan as $x) {
        $ok = $conn->query("INSERT INTO repair01_platform_justification
              (screen_id, label_ar, verdict, criteria_met, roles_rendered, scope_code,
               decision_ref, witness, snapshot_id, measured_at)
            VALUES ('" . $e($x['id']) . "','" . $e(mb_substr($x['label'], 0, 190)) . "','"
             . $e($x['v']) . "','" . $e($x['c']) . "'," . (int) $x['roles'] . ",'"
             . $e($x['scope']) . "','RPR-02 §5.4','" . $e(mb_substr($x['wit'], 0, 600)) . "','"
             . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$x['id']}: {$conn->error}\n"); }
        $n++;
        /* ⛔ **والعودةُ إلى النطاقِ فعلٌ لا وسمٌ** — §٥·٤ تأمر بها نصًّا */
        if ($x['v'] === 'RETURN_TO_SCOPE') {
            $u = $conn->query("UPDATE repair01_screen_registry
                  SET ownership_verdict = '" . $e($x['scope']) . "',
                      verdict_rule = 'RPR02_S54_RETURN_TO_SCOPE',
                      verdict_at = NOW()
                WHERE screen_id = '" . $e($x['id']) . "' AND ownership_verdict = 'PLATFORM_SHARED'");
            if (!$u) { exit("✘ تعذّرت عودةُ {$x['id']}: {$conn->error}\n"); }
            $moved += $conn->affected_rows;
        }
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_platform_justification
                                WHERE witness = ''")->fetch_row()[0];
    $left = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry
                                 WHERE on_disk = 1 AND ownership_verdict = 'PLATFORM_SHARED'")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** سطحًا · صفٌّ بلا شاهدٍ %d\n", $n, $bad);
    printf("  ✔ عاد إلى نطاقِه **%d** سطحًا · والباقي `PLATFORM_SHARED` **%d**\n", $moved, $left);
}

if ($MD) {
    $o  = "# `RPR-02` #١٣ — تبريرُ `PLATFORM` بأربعتِه\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## القاعدةُ الحاكمة\n\n`RPR-02` §٥·٤ — أربعةُ معايير **معًا** لا واحد، ";
    $o .= "**وما لم يستوفِ الأربعةَ يعود إلى إدارتِه من السبعَ عشرة**.\n\n";
    $o .= "| المعيار | مستوفٍ | المقام |\n|---|---:|---:|\n";
    $o .= "| ① خدمةٌ فعليّةٌ لثلاثِ جهاتٍ فأكثر | **$c1n** | $N |\n";
    $o .= "| ② لا يملك حقيقةَ أعمال | **$c2n** | $N |\n";
    $o .= "| ③ مالكٌ تقنيٌّ مسمًّى شخصًا | **$c3n** | $N |\n";
    $o .= "| ④ مسجَّلٌ بمعرِّفِه وقاعدةِ ظهورِه | **$c4n** | $N |\n\n";
    $o .= "⛔ **والمعيارُ ③ حاجزُ بيانٍ لا حاجزُ تحليل**: `repair01_platform_capabilities.tech_owner` ";
    $o .= "فارغٌ في صفِّه الوحيد، ولا جدولَ آخرَ يحمله. **وتسميةُ شخصٍ من عندنا تلفيقٌ** يمنعه ";
    $o .= "§٥ المحظور ④ — فيُكتب `NEEDS_GOVERNING_SOURCE` ويُحسب **مُخِلًّا** لا مستوفًى بالسكوت.\n\n";
    $o .= "## الحكمُ\n\n| الحكم | العدد | المعنى |\n|---|---:|---|\n";
    $o .= "| `PLATFORM_JUSTIFIED` | **" . $stat['J1'] . "** | استوفى الأربعةَ ⇒ تبريرٌ معتمَدٌ بمرجعِ §٥·٤ |\n";
    $o .= "| `RETURN_TO_SCOPE` | **" . $stat['J2'] . "** | أخلَّ بواحدٍ وله نطاقٌ مُعلَنٌ ⇒ **عاد إليه** |\n";
    $o .= "| `NO_SCOPE_TO_RETURN` | **" . $stat['J3'] . "** | أخلَّ بواحدٍ ولا نطاقَ ⇒ يُعلَن بحاجزِه |\n\n";
    $o .= "## الأسطحُ بشواهدِها\n\n";
    foreach ($plan as $x) {
        $o .= "- **`" . $x['id'] . "` · " . $x['label'] . "** — `" . $x['v'] . "` — " . $x['wit'] . "\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S13_JUSTIFY.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S13_JUSTIFY.md\n";
}
