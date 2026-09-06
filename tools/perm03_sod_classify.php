<?php
/**
 * tools/perm03_sod_classify.php — تقييدُ صنفِ تركيبةِ فصلِ الواجباتِ في سجلِّها
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC ق-٧ يشقُّ التركيباتِ صنفَين: **أ** تنفيذُ الطرفَين (بوّابةُ إغلاق)
 * و**ب** تداخلُ رؤيةٍ فقط (مؤشِّرٌ لا حاجز). والشقُّ كان **يُحسَب في أداةِ
 * القياسِ عند كلِّ تشغيلٍ ولا يُقيَّد**: عمودُ `treatment_decision` في
 * `gov_sod_conflict` فارغٌ في كلِّ الصفوف.
 *
 * ⛔ **وحكمٌ لا يُكتب في السجلِّ يُيتِّم شواهدَه**: من يقرأ السجلَّ غدًا يجد
 *   مئةً وعشرين تعارضًا **بلا صنف**، فلا يعرف أيُّها حاجزٌ وأيُّها مؤشِّر —
 *   ويعيد الاشتقاقَ بيدِه فيختلف. [[gap-codes-register-not-prose]]
 *
 * ◆ **والاشتقاقُ من الكتابةِ الفعليّةِ لا من الرأي**: خريطةُ
 *   `perm01_function_screen` تقول من يكتب في مرساةِ كلِّ وظيفة، والقوالبُ
 *   النافذةُ تقول من يملك الكتابةَ على شاشاتِها. والأحكامُ خمسةٌ تُفرَز ولا
 *   تُخلَط — بنصِّ المقياسِ ① حرفًا:
 *     `A`            الصنفُ أ — يملك كتابةَ الطرفَين المتمايزَين ⇒ حاجز
 *     `B`            الصنفُ ب — تداخلُ رؤيةٍ فقط ⇒ مؤشِّر
 *     `same_screen`  طرفاه على الشاشةِ نفسِها ⇒ يستحيل الفصلُ بالشاشة
 *     `unimplemented` وظيفةٌ بلا كاتبٍ في الإنتاج ⇒ غيابُ ميزةٍ لا فصلٌ محقَّق
 *     `document`     حبّتُه المستندُ ⇒ إنفاذُه بسلسلةِ الاعتمادِ لا بالشاشة
 *
 * التشغيل: php tools/perm03_sod_classify.php [--write]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');
$WRITE = in_array('--write', $argv, true);

/* ── ① خريطةُ الوظيفةِ إلى شاشاتِها الكاتبة ─────────────────────────────── */
$fnScreens = array(); $fnState = array();
$r = $db->query("SELECT func_name, screen_code, registered, state FROM perm01_function_screen");
while ($r && ($x = $r->fetch_assoc())) {
    $fnState[$x['func_name']] = $x['state'];
    if ($x['state'] === 'mapped' && (int) $x['registered'] === 1 && $x['screen_code'] !== '') {
        $fnScreens[$x['func_name']][$x['screen_code']] = 1;
    }
}

/* ── ② أعلامُ الكتابةِ لكلِّ قالبٍ نافذ ─────────────────────────────────── */
$profItems = array();
$r = $db->query("SELECT i.profile_id, i.item_ref,
                        (i.can_add | i.can_edit | i.can_delete) w
                   FROM gov_profile_items i
                   JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                  WHERE i.item_kind = 'screen' AND i.allow = 1");
while ($r && ($x = $r->fetch_assoc())) {
    $profItems[(int) $x['profile_id']][(string) $x['item_ref']] = (int) $x['w'];
}

/* ── ③ التركيباتُ وأصنافُها ──────────────────────────────────────────────── */
$pairs = array();
$r = $db->query("SELECT code, func_a, func_b, scope, severity FROM sec_sod_pairs WHERE active = 1");
while ($r && ($x = $r->fetch_assoc())) { $pairs[] = $x; }

$classOf = array(); $tally = array();
foreach ($pairs as $p) {
    $code = (string) $p['code'];
    $fa = (string) $p['func_a']; $fb = (string) $p['func_b'];
    $cls = null;
    if ((string) $p['scope'] === 'document') {
        $cls = 'document';
    } elseif (($fnState[$fa] ?? '') === 'unimplemented' || ($fnState[$fb] ?? '') === 'unimplemented') {
        $cls = 'unimplemented';
    } elseif (!isset($fnScreens[$fa]) || !isset($fnScreens[$fb])) {
        $cls = 'no_anchor';
    } else {
        $exA = array_diff_key($fnScreens[$fa], $fnScreens[$fb]);
        $exB = array_diff_key($fnScreens[$fb], $fnScreens[$fa]);
        if (!$exA || !$exB) {
            $cls = 'same_screen';
        } else {
            $cls = 'B';
            foreach ($profItems as $items) {
                $hA = array_intersect_key($items, $exA);
                $hB = array_intersect_key($items, $exB);
                if (!$hA || !$hB) { continue; }
                $wA = 0; foreach ($hA as $z) { $wA |= $z; }
                $wB = 0; foreach ($hB as $z) { $wB |= $z; }
                if ($wA && $wB) { $cls = 'A'; break; }
            }
        }
    }
    $classOf[$code] = $cls;
    $tally[$cls] = ($tally[$cls] ?? 0) + 1;
}

echo "══ صنفُ التركيباتِ المشتقُّ من الكتابةِ الفعليّة ═══════════════════════\n";
$AR = array('A' => 'أ · تنفيذُ الطرفَين (حاجز)', 'B' => 'ب · تداخلُ رؤيةٍ (مؤشِّر)',
            'same_screen' => 'يستحيل الفصلُ بالشاشة', 'unimplemented' => 'وظيفةٌ غيرُ مبنيّة',
            'no_anchor' => 'بلا مرساةٍ معرَّفة', 'document' => 'حبّتُه المستندُ (سلسلةُ الاعتماد)');
foreach ($tally as $k => $n) { printf("   %-34s %d\n", $AR[$k] ?? $k, $n); }
printf("   %-34s %d تركيبةً\n", 'المجموع', count($pairs));

/* ── ④ التقييدُ في السجلّ ────────────────────────────────────────────────── */
$rows = $db->query("SELECT id, conflict_code, treatment_decision FROM gov_sod_conflict");
$seen = 0; $wrote = 0; $unknown = array();
while ($rows && ($x = $rows->fetch_assoc())) {
    $seen++;
    $code = (string) $x['conflict_code'];
    if (!isset($classOf[$code])) { $unknown[$code] = 1; continue; }
    $want = $classOf[$code];
    if ((string) $x['treatment_decision'] === $want) { continue; }
    if ($WRITE) {
        $st = $db->prepare("UPDATE gov_sod_conflict SET treatment_decision = ? WHERE id = ?");
        $st->bind_param('si', $want, $x['id']);
        $st->execute(); $wrote += $st->affected_rows; $st->close();
    } else {
        $wrote++;
    }
}
echo "\n══ سجلُّ الكشف ══════════════════════════════════════════════════════\n";
printf("   صفوفٌ مقروءة: %d\n", $seen);
printf("   صفوفٌ %s: %d\n", $WRITE ? 'كُتبت' : 'ستُكتب (جفافٌ — أضِف ‎--write‎)', $wrote);
if ($unknown) {
    printf("   ⚠ تركيباتٌ في السجلِّ بلا تعريفٍ نافذٍ في `sec_sod_pairs`: %d (%s)\n",
        count($unknown), implode(' · ', array_slice(array_keys($unknown), 0, 5)));
}

/* ── ⑤ الشاهد: الصنفُ أ هو وحدَه بوّابةُ الإغلاق ────────────────────────── */
$a = $tally['A'] ?? 0;
printf("\n   الصنفُ أ (بوّابةُ الإغلاق): %d — المستهدَف صفر\n", $a);
echo $a === 0
    ? "   ✔ لا فاعلَ يملك تنفيذَ طرفَي تركيبةٍ متمايزةِ الشاشات.\n"
    : "   ⛔ ثمّةَ قالبٌ يجمع كتابةَ الطرفَين — يُنزع علمُ أحدِهما أو يُضبط الفعل.\n";
exit(0);
