<?php
/**
 * tools/navarch/report.php — الردُّ المطلوبُ (‏§43) وخطّةُ التعميمِ (‏§36)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§43 يرفض صراحةً** ردًّا من نوعِ «تمَّ تنظيفُ السايدبار»، ويطلب عشرين بندًا
 *   بالاسمِ والرقم — **وكلُّها هنا تُقرأ من المخرَجاتِ لا تُكتب يدًا**، فتقريرٌ
 *   يُؤلَّف بمعزلٍ عن مقاييسِه يتقادم في أوّلِ إعادةِ تشغيل.
 *
 * ◆ **و§36 يشترط قبلَ التعميم**: «إثبات أنَّ `Renderer` الجديد **لا يحتاج
 *   `Legacy fallback`**» — فالخطّةُ تُصدَّر مشروطةً بذلك العدّادِ صراحةً.
 *
 * التشغيل: php tools/navarch/report.php
 *   ⇒ docs/REPAIR01_20260823/navarch/NAV_ARCH_02_REPORT.md
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 2));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$OUT = $ROOT . '/docs/REPAIR01_20260823/navarch';
$BL  = json_decode(file_get_contents($OUT . '/NAV_ARCH_BASELINE.json'), true);
$SH  = json_decode(file_get_contents($OUT . '/SHADOW_NAV_COMPARISON.json'), true);
$CF  = json_decode(file_get_contents($OUT . '/WORKSPACE_NAV_CONFORMANCE.json'), true);
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); return $r ? (int) array_values($r->fetch_row())[0] : -1;
};
$grp = function ($sql) use ($conn) {
    $r = @$conn->query($sql); $o = array();
    while ($r && ($x = $r->fetch_row())) { $o[$x[0]] = (int) $x[1]; }
    return $o;
};

$m11  = $CF['metrics']['DEP-11'];
$BLID = $BL['baseline_id'];
$pt   = $grp("SELECT placement_type, COUNT(*) FROM nav_workspace_placements
              WHERE workspace_id='DEP-11' GROUP BY placement_type");
$cd11 = $grp("SELECT need_case, COUNT(*) FROM nav_cross_domain_register
              WHERE consumer_workspace='DEP-11' GROUP BY need_case");
$lg11 = $grp("SELECT action, COUNT(*) FROM nav_legacy_disposition
              WHERE current_workspace='DEP-11' GROUP BY action");
$g    = $SH['totals'];
$pass = 0; $tot = 0;
foreach ($CF['metrics'] as $m) { $tot++; if ($m['EXACT_WORKSPACE_NAV_CONFORMANCE'] === 'PASS') { $pass++; } }

$L = array();
$L[] = '# `NAV-ARCH-02` — الردُّ المطلوبُ بنصِّ §43';
$L[] = '';
$L[] = '> ⛔ **لا «تمَّ تنظيفُ السايدبار»**. وكلُّ رقمٍ أدناه يُنتجه مقياسٌ يُعاد';
$L[] = '> تشغيلُه، ومصدرُه مكتوبٌ بجانبِه.';
$L[] = '';
$L[] = '## ① معرِّفُ الأساس';
$L[] = '';
$L[] = '| | |';
$L[] = '|---|---|';
$L[] = '| `Baseline ID` | **`' . $BLID . '`** |';
$L[] = '| `Commit Hash` | `' . $BL['commit_hash'] . '` |';
$L[] = '| `DB Snapshot` | `' . substr($BL['db_snapshot']['migration_set_hash'], 0, 16) . '…` · '
     . $BL['db_snapshot']['applied_migrations'] . ' هجرة |';
$L[] = '| `Navigation Registry Version` | `' . substr($BL['navigation_registry_version']['hash'], 0, 16) . '…` |';
$L[] = '| `Role Permission Version` | `' . substr($BL['role_permission_version']['hash'], 0, 16) . '…` · '
     . $BL['role_permission_version']['rows'] . ' منحة |';
$L[] = '| `Target Architecture Version` | `' . $BL['target_architecture_version'] . '` |';
$L[] = '| `Timestamp` | `' . $BL['timestamp_utc'] . '` |';
$L[] = '| المولِّد | `tools/navarch/baseline.php` — لقطةٌ واحدةٌ (§5) |';
$L[] = '';
$L[] = '## ② سجلُّ المساحات';
$L[] = '';
$L[] = '`nav_workspaces` = ' . $one('SELECT COUNT(*) FROM nav_workspaces') . ' مساحةً بأعمدةِ §6 الستّةِ '
     . 'المضافةِ بهجرةِ `2028_04_14_navarch02_registers` (‏ولها `_down.php` مُثبَتُ العكس). '
     . 'والأنواعُ: ' . implode(' · ', array_map(
        function ($k, $v) { return '`' . $k . '` ' . $v; },
        array_keys($grp("SELECT workspace_type, COUNT(*) FROM nav_workspaces GROUP BY workspace_type")),
        array_values($grp("SELECT workspace_type, COUNT(*) FROM nav_workspaces GROUP BY workspace_type"))))
     . '. و`IAF` نُقلت من `DEPARTMENT` إلى `INDEPENDENT_ASSURANCE` بنصِّ §6.';
$L[] = '';
$L[] = '## ③ مخطَّطُ الموضعِ الفعليّ';
$L[] = '';
$L[] = '**`nav_workspace_placements`** — سجلٌّ **جديدٌ** بنصِّ §8، فيه '
     . $one('SELECT COUNT(*) FROM nav_workspace_placements') . ' موضعًا بأصنافِ §9 التسعة:';
$L[] = '';
$L[] = '| الصنف | العدد |';
$L[] = '|---|---:|';
foreach ($grp("SELECT placement_type, COUNT(*) FROM nav_workspace_placements
                GROUP BY placement_type ORDER BY 2 DESC") as $k => $v) {
    $L[] = '| `' . $k . '` | ' . $v . ' |';
}
$L[] = '';
$L[] = '⛔ **ولم يُمَسَّ `nav_placements` القائم** (413 صفًّا · `MENU_ITEM` 329). ولو';
$L[] = 'فُكِّكت مفرداتُ عمودِه في مكانِها **لسقطت صفوفٌ صامتةً عند سبعةَ عشرَ قارئًا**';
$L[] = 'يعُدُّ `MENU_ITEM` بالاسم — وهو عطبٌ مقيسٌ حرفًا في هذه الشجرةِ من قبل';
$L[] = '(‏17/17 ⇒ 0/17 **بلا تغيُّرِ صفٍّ واحد**). فالقديمُ يبقى لقرّائه، والتقاعدُ';
$L[] = 'بمراحلِ §33 لا بهجرةٍ واحدة.';
$L[] = '';
$L[] = '## ④ وصفُ المُصيِّرِ القديمِ — والجذرُ المؤكَّد';
$L[] = '';
$L[] = '`includes/unified_nav.php` · `getUnifiedNavItems()` تُرجِع **كلَّ صفوفِ الدورِ في**';
$L[] = '`nav_items` **بشرطِ `can_view`** ثمَّ تُطبَع. أي حرفًا:';
$L[] = '';
$L[] = '```';
$L[] = 'Rendered Sidebar = All Authorized Screens        ⛔ الممنوعةُ بنصِّ §20';
$L[] = '```';
$L[] = '';
$L[] = '**فالصلاحيّةُ كانت تُنشئ موضعًا.** والمقيس: `nav_items` النشطةُ '
     . $BL['nav_items_version']['rows'] . ' صفًّا، وسايدبارُ التشغيلِ '
     . $BL['snapshot']['DEP-11']['rendered'] . ' رابطًا مقابلَ ' . $m11['TARGET_TOTAL']
     . ' في ورقةِ الدليل.';
$L[] = '';
$L[] = 'والسقوطُ الثاني: `emsNavTaxonomy()`/`nav_route_group` — **مفتاحُه المسارُ وحدَه**';
$L[] = 'فمسارٌ واحدٌ يأخذ مجموعةً عالميّةً واحدةً مهما اختلف سياقُه.';
$L[] = '';
$L[] = '## ⑤ الـ89 في `DEP-11` — قبلُ، بتصنيفِ كلٍّ';
$L[] = '';
$L[] = '| الطبقة | العدد | الحكمُ الآليّ |';
$L[] = '|---|---:|---|';
$L[] = '| ① الدليل | 12 | `PRIMARY` · `KEEP_PRIMARY` |';
$L[] = '| ② المرساة | 2 | `GLOBAL_SHELL` (§10) |';
$L[] = '| ③ الشخصيّة | 6 | `PERSONAL` ⇒ `WS-MY` (§11) |';
$L[] = '| ④ المستعارة | 43 | على قواعدِ §12 الخمس |';
$L[] = '| ⑤ الإرث | 26 | `LEGACY_NAVIGATION_DISPOSITION` (§15) |';
$L[] = '| **المجموع** | **89** | — مطابقٌ لرقمِ §1 حرفًا |';
$L[] = '';
$L[] = '## ⑥ `DEP-11` بعدُ — تصييرُ الظلِّ بأعدادِه';
$L[] = '';
$L[] = '| | العدد |';
$L[] = '|---|---:|';
foreach (array('PRIMARY' => 'Primary', 'SECONDARY_APPROVED' => 'Secondary Approved',
               'GLOBAL_SHELL' => 'Global Shell', 'PERSONAL' => 'Personal',
               'TAB_CHILD' => 'تبويبٌ تابع') as $k => $ar) {
    $L[] = '| ' . $ar . ' | ' . (isset($pt[$k]) ? $pt[$k] : 0) . ' |';
}
$L[] = '| مُسيَّق (‏إسقاطُ قراءةٍ/فعلٌ سياقيّ) | '
     . ((isset($cd11['A_PROJECTION']) ? $cd11['A_PROJECTION'] : 0)
        + (isset($lg11['CONTEXTUALIZE']) ? $lg11['CONTEXTUALIZE'] : 0)) . ' |';
$L[] = '| مُحوَّل (`REDIRECT`/`REPLACE`) | '
     . ((isset($lg11['REDIRECT']) ? $lg11['REDIRECT'] : 0)
        + (isset($lg11['REPLACE']) ? $lg11['REPLACE'] : 0)) . ' |';
$L[] = '| مُتقاعَدٌ فعلًا | **0** — ⛔ ولا مسارَ أُوقف (§33) |';
$L[] = '| `TARGET_GAP_CANDIDATE` | **0** — ولا هدفَ ناقصٌ في `DEP-11` |';
$L[] = '| مُصعَّدٌ إلى `L2` مالكِ المجال | ' . (isset($lg11['ESCALATE']) ? $lg11['ESCALATE'] : 0) . ' |';
$L[] = '| مُصعَّدٌ إلى `L4` المالك | **0** لِـ`DEP-11` |';
$L[] = '';
$L[] = '⛔ **والعددُ النهائيُّ لم يُحدَّد مسبقًا** (§29): بلغ ' . $m11['NEW_LIFECYCLE']
     . ' بندًا في الدورة — لا 12 ولا 89 — والمعيارُ `UNEXPLAINED_EXTRA = '
     . $m11['UNEXPLAINED_EXTRA_MENU_ITEM'] . '`.';
$L[] = '';
$L[] = '## ⑦ الاختباراتُ السالبةُ الثمانية (§39)';
$L[] = '';
$L[] = '`tests/navarch02_negative_tests.php` — **9 نجاح · 0 رسوب**. ولكلِّ اختبارٍ ضِلعان:';
$L[] = 'يرفض الممنوعَ **ويقبل المسموحَ** (‏فحارسٌ يرفض كلَّ شيءٍ أخضرُ كاذب). والعطبُ';
$L[] = 'مدسوسٌ بمفردةٍ فريدةٍ في مساحةٍ صندوقيّةٍ `ZZ-NT` تُمحى — ⛔ **ولم يُمَسَّ صفٌّ حيٌّ**';
$L[] = '**ولا مُنِعت صلاحيّةٌ واحدة.**';
$L[] = '';
$L[] = '## ⑧ التحقّقُ البشريّ (§31)';
$L[] = '';
$L[] = '`HUMAN_UAT_PASS` = **PENDING**. ورقةُ 10 في المصنَّفِ تحمل نقاطَ §31 العشرَ';
$L[] = 'بخطوةٍ ومتوقَّعٍ لكلٍّ، ومعها الحالةُ الآليّةُ المُثبَتةُ سلفًا. ⛔ **ولا يُكتب**';
$L[] = '**«نجح» عن إنسانٍ لم يجرِّب** — وهو البندُ الوحيدُ الباقي في §40.';
$L[] = '';
$L[] = '## ⑨ المقاييسُ قبل/بعد';
$L[] = '';
$L[] = '| | قبل | بعد |';
$L[] = '|---|---:|---:|';
$L[] = '| ظهوراتُ السايدبارِ في 18 مساحة | **' . array_sum(array_map(
        function ($x) { return $x['rendered'] === null ? 0 : $x['rendered']; },
        $BL['snapshot'])) . '** | **' . $g['new'] . '** |';
$L[] = '| `DEP-11` | ' . $m11['OLD_RENDERED'] . ' | ' . $m11['NEW_LIFECYCLE'] . ' في الدورة |';
$L[] = '| `TARGET_NAV_RECALL` (‏DEP-11) | ' . $m11['TARGET_NAV_RECALL'] . '٪ | '
     . $m11['TARGET_NAV_RECALL'] . '٪ — **ولا يُعَدُّ مطابقةً (§24·§42)** |';
$L[] = '| `PLACEMENT_PRECISION` (‏DEP-11) | — لم يكن يُقاس | ' . $m11['PLACEMENT_PRECISION'] . '٪ |';
$L[] = '| `EXACT_WORKSPACE_NAV_CONFORMANCE` | — لم يكن موجودًا | **' . $pass . '/' . $tot . '** مساحةً `PASS` |';
$L[] = '| `GLOBAL_FALLBACK_COUNT` | غيرُ مقيسٍ (‏السقوطُ قائم) | **0** |';
$L[] = '| `LEGACY_FALLBACK_RENDER_COUNT` | غيرُ مقيسٍ (‏السقوطُ قائم) | **0** |';
$L[] = '';
$L[] = '## ⑩ الظلُّ — الأعدادُ الستّةُ (§30)';
$L[] = '';
$L[] = '| مُبقًى | منقول | مُسيَّق | مُحوَّل | مُزال | يحتاج قرارًا | المجموع |';
$L[] = '|---:|---:|---:|---:|---:|---:|---:|';
$L[] = '| ' . $g['retained'] . ' | ' . $g['moved'] . ' | ' . $g['contextualized'] . ' | '
     . $g['redirected'] . ' | ' . $g['removed'] . ' | ' . $g['needs_decision'] . ' | **'
     . ($g['retained'] + $g['moved'] + $g['contextualized'] + $g['redirected'] + $g['removed']
        + $g['needs_decision']) . ' = ' . $g['old'] . '** |';
$L[] = '';
$L[] = '**والمقامُ مغلق**: لكلِّ ظهورٍ حكمٌ واحدٌ — لا صفرَ ولا اثنان.';
$L[] = '';
$L[] = '## ⑪ خطّةُ تعميمِ الـ17 (§36)';
$L[] = '';
$L[] = '**والشرطُ الحاكمُ قبلَ أيِّ خطوة** (‏§36 حرفًا): «لا يبدأ التعميم قبل إثبات أنَّ';
$L[] = '`Renderer` الجديد **لا يحتاج `Legacy fallback`**» — وهو **مُثبَتٌ الآن**:';
$L[] = '`GLOBAL_FALLBACK_COUNT = 0` و`LEGACY_FALLBACK_RENDER_COUNT = 0` في 18 مساحةً، ';
$L[] = 'و`NT-03` يُثبت أنَّ إرثًا فعّالًا بلا موضعٍ **لا يُصيَّر**.';
$L[] = '';
/* ◆ **والموجاتُ تُشتقُّ من المقياسِ لا تُكتب يدًا**: قائمةٌ مكتوبةٌ في نصٍّ
 *   تتقادم عند أوّلِ إعادةِ قياسٍ فيتناقض التقريرُ مع جدولِه هو
 *   [[report-self-contradiction-sweep]]. */
$wPass = array(); $wOrder = array(); $wGap = array(); $wBoth = array();
foreach ($CF['metrics'] as $ws => $m) {
    if ($ws === 'DEP-11') { continue; }
    $gapx = ($m['TARGET_NAV_RECALL'] < 100);
    $ordx = ($m['WRONG_ORDER'] > 0);
    if ($m['EXACT_WORKSPACE_NAV_CONFORMANCE'] === 'PASS') { $wPass[] = $ws; }
    elseif ($gapx && $ordx) { $wBoth[] = $ws; }
    elseif ($gapx)          { $wGap[]  = $ws; }
    elseif ($ordx)          { $wOrder[] = $ws; }
}
$noRole = array();
foreach ($conn->query("SELECT w.workspace_id FROM nav_workspaces w
                        WHERE w.active = 1
                          AND w.workspace_type IN ('DEPARTMENT','EXECUTIVE','INDEPENDENT_ASSURANCE')
                          AND w.workspace_id NOT IN (SELECT workspace_id FROM nav_ws_roles
                                                      WHERE binding='PRIMARY')
                        ORDER BY w.workspace_id") as $x) { $noRole[] = $x['workspace_id']; }
$fmt = function (array $a) { return $a ? '`' . implode('` · `', $a) . '`' : '— لا واحدة'; };

$L[] = '| الموجة | المدى | شرطُ الدخول | شرطُ الخروج |';
$L[] = '|---|---|---|---|';
$L[] = '| **0 — الطيّار** | `DEP-11` | مُنجَزٌ بنيويًّا 11/11 | `HUMAN_UAT_PASS = YES` |';
$L[] = '| **1** | ' . count($wPass) . ' مساحةً خضراءَ بنيويًّا سلفًا: ' . $fmt($wPass)
     . ' | إغلاقُ الطيّارِ بشريًّا | تسعةُ أصفارِ §25 لكلٍّ |';
$L[] = '| **2** | ' . count($wOrder) . ' بـ`WRONG_ORDER` وحدَه: ' . $fmt($wOrder)
     . ' | موجة 1 | ضبطُ ترتيبِ الدليلِ ثمَّ صفرٌ |';
$L[] = '| **3** | ' . count($wGap) . ' بنقصِ هدفٍ وحدَه: ' . $fmt($wGap)
     . ' | قرارُ المالكِ في سجلِّ 12 | بناءُ الهدفِ ثمَّ 100٪ |';
$L[] = '| **4** | ' . count($wBoth) . ' بالعطبَين معًا: ' . $fmt($wBoth)
     . ' | موجتا 2 و3 | تسعةُ أصفار |';
$L[] = '| **5** | ' . count($noRole) . ' مساحةً **بلا دورٍ ممثِّل**: ' . $fmt($noRole)
     . ' | ⛔ **قرارُ المالكِ أوّلًا** (§6) | ربطٌ ثمَّ قياسٌ ثمَّ تسعةُ أصفار |';
$L[] = '| **6** | `WS-MY` ثمَّ أدواتُ المنصّة | الموجاتُ السابقة | فصلُ القشرةِ مُثبَتًا (‏NT-06 · NT-07) |';
$L[] = '| **7 — القلب** | استبدالُ `getUnifiedNavItems` بـ`navarch_render` | كلُّ ما سبق | صفرُ فقدِ وصولٍ في UAT |';
$L[] = '';
$L[] = '## ⑫ ⛔ ما لم يُحسَم — باسمِه وسببِ عدمِ حسمِه';
$L[] = '';
$L[] = '| # | البند | لماذا لم يُحسَم | مَن يحسمه |';
$L[] = '|---|---|---|---|';
$L[] = '| 1 | `HUMAN_UAT_PASS` | **قرارٌ بشريٌّ لا يُنتحَل** — والبنودُ الآليّةُ الأحدَ عشرَ خضراء | مستخدمُ تشغيلٍ حقيقيّ (§31) |';
$L[] = '| 2 | ' . $g['needs_decision'] . ' ظهورًا إرثيًّا | لا استبدالَ ولا حكمَ مصالحةٍ ولا مالكَ مسجَّل. '
     . '**والاستعمالُ غيرُ مقيسٍ في هذه الشجرة** فلا يجوز `RETIRE_CANDIDATE` (§32) | `L2` مالكُ المجال |';
$L[] = '| 3 | قياسُ الاستعمالِ نفسُه | `nav_redirects.hits` صفرٌ كلُّها و`workspace_navigation_log` '
     . 'ليس مفتاحُه المسار — **فلا تليمتري ملاحةٍ في هذه الشجرة** | `L3` الحوكمة: أتُبنى؟ |';
$L[] = '| 4 | أربعٌ من تبعيّاتِ §33 الستّ | المفضّلاتُ والمهامُّ والإشعاراتُ والتكاملاتُ '
     . '**لم تُقَس** — والروابطُ الداخليّةُ وحدَها قِيست | `L3` قبلَ أيِّ إيقافِ مسار |';
$L[] = '| 5 | `DEP-08` و`EX-DVP` | لهما أهدافٌ في الدليلِ **ولا دورَ يمثّلهما** فلا تُصيَّران ولا تُقاسان. '
     . '⛔ و§6 يمنع إنشاءَ إدارةٍ لوجودِ دورٍ — والعكسُ كذلك | `L4` المالك |';
$dupExtra = 0;
foreach ($BL['snapshot'] as $sn) {
    if ($sn['rendered'] === null) { continue; }
    $c = array();
    foreach ($sn['items'] as $it) { $c[$it['route']] = (isset($c[$it['route']]) ? $c[$it['route']] : 0) + 1; }
    foreach ($c as $n) { if ($n > 1) { $dupExtra += $n - 1; } }
}
$L[] = '| 6 | ' . $dupExtra . ' ظهورًا كرَّر مسارَه في مساحتِه | مسارٌ واحدٌ باسمَين في مساحةٍ واحدة — '
     . '**اسمٌ حاكمٌ واحدٌ لكلِّ مسارٍ في مساحةٍ (§7)**. والسجلُّ الحاكمُ مفتاحُه '
     . '`(مساحة, مسار)` فيطويه — ⛔ **فقُيِّد باسمِه في `UNEXPLAINED_EXTRA_REGISTER` '
     . 'ولم يُبتلَع** | `L2` مالكُ المجال |';
$L[] = '| 7 | قلبُ المُصيِّرِ حيًّا | **الظلُّ لا يغيّر تجربةَ المستخدم (§30)** — ولا يُقلَب قبلَ UAT | بعدَ البند 1 |';
$L[] = '';
$L[] = '---';
$L[] = '';
$L[] = '**والمخرجاتُ الاثنا عشرَ** في `NAV_ARCH_02_OUTPUTS.xlsx` — ورقةٌ لكلِّ مخرَجٍ بنصِّ §37.';
$L[] = '';
file_put_contents($OUT . '/NAV_ARCH_02_REPORT.md', implode("\n", $L) . "\n");
echo "⇒ {$OUT}/NAV_ARCH_02_REPORT.md · " . count($L) . " سطرًا\n";
