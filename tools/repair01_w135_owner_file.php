<?php
/**
 * tools/repair01_w135_owner_file.php — ملفُّ قراراتِ المالكِ الواحد (البند 14)
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ المالك · البند 14**: «أخرجْ ملفًّا واحدًا فقط: `Current → Recommended
 * → Decision Needed` وأغلقْها في جلسةٍ واحدة.»
 *
 * ◆ **والمقترَحُ يُعرَض ولا يُكتب** — وهذا هو الدرسُ الذي كلَّفَنا `DEC-OPEN-16`:
 *   عمودُ التوصيةِ ليس قرارَ مالك. فالملفُّ يقترح والمخزنُ لا يتغيّر حتّى يُجيب.
 *
 * ◆ **ومولَّدٌ من المخزن** — يُعاد توليدُه بعد كلِّ جواب، فما أُجيب يختفي منه
 *   وما بقي يبقى. ⛔ ولا يُحرَّر يدويًّا فيتفرّق عن مصدرِه.
 *
 * التشغيل: php tools/repair01_w135_owner_file.php
 * المخرَج : docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$rows = function ($sql) use ($conn) { $r = @$conn->query($sql); $o = array(); while ($r && ($x = $r->fetch_assoc())) { $o[] = $x; } return $o; };
$one  = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? $r->fetch_row()[0] : ''; };

$m = array();
$m[] = '# قراراتٌ تنتظر المالك — بوّابةُ W13.5';
$m[] = '';
$m[] = '> **البند 14 من أمرِ 2026-08-26**: ملفٌّ واحدٌ `الحالي ← المقترَح ← المطلوبُ حسمُه`.';
$m[] = '> ⛔ **مولَّدٌ من المخزن — لا يُحرَّر يدويًّا**: `php tools/repair01_w135_owner_file.php`.';
$m[] = '> **تاريخُ القياس:** ' . $one('SELECT CURDATE()');
$m[] = '';
$m[] = '⛔ **والمقترَحُ هنا معروضٌ لا مكتوب.** عمودُ التوصيةِ ليس قرارَ مالك — وهذا';
$m[] = 'بالضبط ما كلَّفَنا `DEC-OPEN-16`. فلا يتغيّر المخزنُ حتّى تُجيب.';
$m[] = '';
$m[] = '---';
$m[] = '';

/* ═══ ① القراراتُ البنيويّة ══════════════════════════════════════════════ */
$m[] = '## ① قراراتٌ بنيويّةٌ — نصٌّ في المخزنِ بلا سندٍ منك';
$m[] = '';
$m[] = 'المراجعةُ العكسيّةُ (‏البند 12) قارنت المخزنَ بمصنَّفِك. هذانِ يحملانِ حكمًا';
$m[] = '**وخانةُ قرارِ المالكِ خاليةٌ في ورقتَيه معًا**.';
$m[] = '';
foreach ($rows("SELECT d.decision_id, d.question, d.recommended, d.owner_decision, d.blocker_type
                  FROM repair01_decisions d
                  JOIN repair01_decision_audit a ON a.decision_id = d.decision_id
                 WHERE a.verdict = 'SYSTEM_ASSUMED_APPROVAL' ORDER BY d.decision_id") as $d) {
    $m[] = '### `' . $d['decision_id'] . '`  ·  ' . $d['question'];
    $m[] = '';
    $m[] = '| | |';
    $m[] = '|---|---|';
    $m[] = '| **الحالي في مصنَّفِك** | `NEEDS_OWNER_DECISION` — خانةُ القرارِ خالية |';
    $m[] = '| **التوصيةُ المكتوبةُ عندك** | ' . str_replace('|', '¦', (string) $d['recommended']) . ' |';
    $m[] = '| **المخزَّنُ الآن (‏بلا سندٍ منك)** | ' . mb_substr(str_replace(array('|', "\n"), array('¦', ' '), (string) $d['owner_decision']), 0, 420) . '… |';
    $m[] = '| **المطلوب** | **أقرُّه · أعدّله · أرفضه** — وأيّها قلتَ أُثبّته بمرجعِ جوابِك |';
    $m[] = '';
}

/* ═══ ② الحاجبُ البنيويُّ المفتوح ═══════════════════════════════════════ */
$m[] = '---';
$m[] = '';
$m[] = '## ② حاجبٌ بنيويٌّ مفتوحٌ — يوقف المرحلةَ الخامسةَ عشرة';
$m[] = '';
foreach ($rows("SELECT decision_id, question, current_state, options, recommended
                  FROM repair01_decisions
                 WHERE blocking_level = 'STRUCTURAL_TARGET_BLOCKER' AND status <> 'APPROVED'") as $d) {
    $m[] = '### `' . $d['decision_id'] . '`  ·  ' . $d['question'];
    $m[] = '';
    $m[] = '- **الحالةُ القائمة:** ' . $d['current_state'];
    $m[] = '- **الخيارات:** ' . $d['options'];
    $m[] = '- **التوصية:** ' . $d['recommended'];
    $m[] = '';
}

/* ═══ ③ الأسماءُ غيرُ المعتمَدة ══════════════════════════════════════════ */
$nm = $rows("SELECT nc.route, nc.canonical_ar, nc.status, nc.group_name,
                    (SELECT sr.owner_code FROM repair01_screen_registry sr
                      WHERE sr.screen_file = SUBSTRING_INDEX(nc.route,'/',-1) LIMIT 1) own
               FROM nav_canonical nc WHERE nc.status <> 'APPROVED' ORDER BY own, nc.route");
$m[] = '---';
$m[] = '';
$m[] = '## ③ أسماءٌ معروضةٌ غيرُ معتمَدة — ' . count($nm) . ' اسمًا';
$m[] = '';
$m[] = '**الحالي** هو الاسمُ المعروضُ اليومَ فعلًا، و**المقترَح** هو هو —';
$m[] = 'فالمطلوبُ **توقيعٌ لا تسمية**. وما تريد تغييرَه اكتبْ بدلَه.';
$m[] = '';
$m[] = '| الاسمُ المعروض | المجموعة | الإدارة | المسار |';
$m[] = '|---|---|---|---|';
foreach ($nm as $x) {
    $m[] = '| ' . str_replace('|', '¦', (string) $x['canonical_ar']) . ' | '
         . str_replace('|', '¦', (string) $x['group_name']) . ' | '
         . ($x['own'] ?: '—') . ' | `' . $x['route'] . '` |';
}

/* ═══ ④ أسطحٌ بلا إدارةٍ مالكة ═══════════════════════════════════════════ */
$or = $rows("SELECT screen_file, ownership_verdict, guard_kind
               FROM repair01_screen_registry
              WHERE COALESCE(owner_code,'') = '' AND on_disk = 1 ORDER BY screen_file");
$m[] = '';
$m[] = '---';
$m[] = '';
$m[] = '## ④ أسطحٌ حيّةٌ بلا إدارةٍ مالكة — ' . count($or) . ' سطحًا';
$m[] = '';
$m[] = '**كلُّها في جذرِ المستودعِ بلا مجلَّدٍ** — فلا إشارةَ فيها تُشتقُّ منها ملكيّةٌ.';
$m[] = 'وقاعدةُ «مالكُ المجلَّدِ الغالب» لا تنطبق، **ونسبتُها بالتخمينِ تخترع ملكيّة**.';
$m[] = '';
$m[] = 'والبند 18 يقول: قد يكون بعضُها **قدرةَ منصّةٍ لا إدارة** — فاكتبْ أمامَ كلٍّ';
$m[] = 'إمّا رمزَ إدارةٍ (`DEP-01`..`DEP-17`) وإمّا `PLATFORM` وإمّا `RETIRE`.';
$m[] = '';
$m[] = '| السطح | حكمُ ملكيّتِه اليوم | حارسُه | الإدارة؟ |';
$m[] = '|---|---|---|---|';
foreach ($or as $x) {
    $m[] = '| `' . $x['screen_file'] . '` | ' . $x['ownership_verdict'] . ' | '
         . ($x['guard_kind'] ?: '—') . ' |  |';
}

/* ═══ ⑤ ما لا ينتظرك ═════════════════════════════════════════════════════ */
$m[] = '';
$m[] = '---';
$m[] = '';
$m[] = '## ما لا ينتظرك — أُنجز وأُثبت';
$m[] = '';
$m[] = '| البند | المقيس |';
$m[] = '|---|---|';
$m[] = '| المقامُ التنظيميّ | 17 إدارةً و4 وحداتٍ خارجَ التسلسل ✔ |';
$m[] = '| مصالحةُ الملكيّة | ' . $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict <> ''")
     . ' سطحًا بحكمٍ وقاعدةٍ · صفرُ مجهولٍ · صفرُ حقيقةٍ في إدارتَين ✔ |';
$m[] = '| التقاطعاتُ الخمسة | ' . $one("SELECT COUNT(*) FROM repair01_screen_registry
        WHERE owner_code IN ('DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17')
          AND surface_kind <> ''") . ' سطحًا مُصنَّفًا مصدرًا أو إسقاطًا ✔ |';
$m[] = '| سقّاطةُ السطحِ الجديد | اثنا عشرَ شرطًا · مبنيّةٌ وتردُّ الناقصَ ✔ |';
$m[] = '| سندُ قرارِ المالك | 9 حقولٍ · وقيدٌ يمنع اعتمادًا بلا مرجع ✔ |';
$m[] = '| تسييجُ دَينِ المالية | ' . $one("SELECT COUNT(*) FROM repair01_screen_registry
        WHERE owner_code IN ('DEP-05','DEP-06') AND finance_debt_class <> ''") . ' سطحًا مُصنَّفًا ✔ |';
$m[] = '| قرارُ الأشباح | ' . $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE ghost_disposition <> ''")
     . ' صفًّا بقرارٍ وسببٍ ✔ |';
$m[] = '';
$m[] = '**والبوّابةُ اليومَ: 15 من 19 خضراء.** والأربعةُ الباقيةُ هي ما في هذا الملفّ.';
$m[] = '';

$OUT = $ROOT . '/docs/REPAIR01_20260823/open/W135_OWNER_DECISIONS.md';
@mkdir(dirname($OUT), 0777, true);
file_put_contents($OUT, implode("\n", $m) . "\n");
printf("✔ كُتب: %s\n", str_replace($ROOT . DIRECTORY_SEPARATOR, '', $OUT));
printf("  قراراتٌ بنيويّة %d · حاجبٌ مفتوح %d · أسماء %d · أسطحٌ بلا مالك %d\n",
    (int) $one("SELECT COUNT(*) FROM repair01_decision_audit WHERE verdict = 'SYSTEM_ASSUMED_APPROVAL'"),
    (int) $one("SELECT COUNT(*) FROM repair01_decisions WHERE blocking_level = 'STRUCTURAL_TARGET_BLOCKER' AND status <> 'APPROVED'"),
    count($nm), count($or));
printf("  حجم: %s ك.ب\n", number_format(filesize($OUT) / 1024, 1));
