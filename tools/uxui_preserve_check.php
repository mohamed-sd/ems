<?php
/**
 * tools/uxui_preserve_check.php — مصفوفةُ الحفظِ: قبلَ الترحيلِ وبعدَه عددًا بعدد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ القاعدةُ الأولى (ف٤ + بوابة ١٣): «لا يُحذف زرٌّ ولا شاشةٌ ولا عمودٌ ولا
 *   مرشِّحٌ ولا رابط — ومصفوفةُ الحفظِ تُبنى قبلَ الترحيلِ وتُقارَن بعدَه
 *   عددًا بعدد، والنقصُ يُرسِّب التسليم.»
 * ◆ نطاقُ هذه الأداة: روابطُ السايدبارِ المُصيَّرةُ لكلِّ دورٍ جذريّ.
 *   الأساسُ القبليُّ الملتزم: docs/uxui_live_positions.tsv (911 موضعًا · 19 دورًا
 *   · جلساتٌ حقيقية co4 · التزام 382a0b7 قبل أيِّ مساسٍ بالمولِّد).
 * ◆ هويةُ البندِ الظاهرِ للمستخدم = (الملفُّ الأمُّ + الاسمُ المعروض) — لا صيغةُ
 *   href: فالمرساةُ `#2` والمنظرُ `?view=` لملفٍّ واحدٍ باسمٍ واحدٍ مدخلٌ واحدٌ
 *   (قانونُ حارسِ التوأم INJ-0459 قبل الجولةِ وبعدَها). والقانون:
 *   ① كلُّ بندٍ قبليٍّ يجب أن يوجد بعديًّا بالهويةِ نفسِها —
 *   ② أو باسمِه المعياريِّ إن كان اسمُه القبليُّ من «المسمياتِ الملغاة» لصفِّ
 *     مسارِه في السجلِّ المعتمَد (إعادةُ التسميةِ من المسموحاتِ الأربعة) —
 *   ③ وما عدا ذلك نقصٌ يُرسِّب. والزائدُ (صفٌّ كان يبتلعه الحارسُ وعاد
 *     بتمايزِ الاسمِ المعياري) يُبلَّغ ولا يُرسِّب — فالقاعدةُ ضدَّ الفقدِ.
 *
 * التشغيل (قراءةٌ فقط):
 *   php tools/uxui_preserve_check.php              المقارنةُ وتقريرُها
 *   php tools/uxui_preserve_check.php --gate       رمزُ خروجٍ 1 عند أيِّ نقص
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$GATE = in_array('--gate', $argv, true);
$base = $ROOT . '/docs/uxui_live_positions.tsv';
if (!is_file($base)) { fwrite(STDERR, "لا أساسَ قبليًّا: {$base}\n"); exit(2); }

/* ── الأدوارُ المؤرشَفةُ بإثباتٍ — تُستثنى من المقارنةِ ولا تُضعِف البوابة ──
   البوابةُ لا تفرّق بين فقدٍ وأرشفةٍ مقصودة، فالاستثناءُ يُصرَّح **من القاعدةِ
   نفسِها** لا بقائمةٍ في الكود: دورٌ كلُّ صفوفِه محجورةٌ في nav_items_archive_*
   بمستندِ إثباتٍ وموعدِ حذف. ولو أُلغيت الأرشفةُ عاد الدورُ للمقارنةِ تلقائيًّا،
   ولو ضاع رابطٌ من دورٍ غيرِ مؤرشَفٍ رسّب كما كان. */
$archivedRoles = array();
$ar = @mysqli_query($conn, "SELECT role_id, COUNT(*) c, MIN(purge_after) p, MIN(proof_doc) d
                              FROM nav_items_archive_role5 GROUP BY role_id");
while ($ar && ($x = mysqli_fetch_assoc($ar))) {
    $live = @mysqli_query($conn, "SELECT COUNT(*) c FROM nav_items WHERE role_id = " . (int) $x['role_id'] . " AND active = 1");
    $liveN = $live ? (int) mysqli_fetch_assoc($live)['c'] : -1;
    if ($liveN === 0) { $archivedRoles[(int) $x['role_id']] = array('n' => (int) $x['c'], 'purge' => $x['p'], 'proof' => $x['d']); }
}

/* ── السجلُّ المعتمَد: لكلِّ مسارٍ APPROVED اسمُه المعياريُّ ومسمياتُه الملغاة ── */
$canon = array();   // file_lc => ['canon' => الاسم, 'old' => [الملغاة…]]
$res = mysqli_query($conn, "SELECT route, canonical_ar, old_names FROM nav_canonical WHERE status = 'APPROVED'");
while ($res && ($x = mysqli_fetch_assoc($res))) {
    $olds = array();
    foreach (preg_split('/[\/·]+/u', (string) $x['old_names']) as $o) {
        $o = trim($o);
        if ($o !== '') { $olds[$o] = true; }
    }
    $canon[strtolower(trim($x['route']))] = array('canon' => trim($x['canonical_ar']), 'old' => $olds);
}

/* ── الدمجُ المُعلَن: مسارٌ ذاب في آخرَ ليس فقدًا — بشرطَين مقيسَين ─────────
   ◆ **الثغرةُ التي يسدُّها** — وقعت فعلًا: `Tickets/dept_inbox.php` دُمج تبويبًا
     في `Tickets/tickets_list.php?tab=dept` (هجرة 2027_07_24)، فاختفى ملفُّه من
     سايدبارِ سبعةَ عشرَ دورًا. وقاعدةُ هذه الأداةِ «ملفٌّ اختفى من الدور = فقدٌ
     صريح» لا تعرف الدمجَ فرسّبت التسليمَ — والوصولُ لم ينقص حرفًا: كلُّ دورٍ
     منها يحمل التبويبَ الوارثَ في المكانِ نفسِه.
   ◆ **ولا يُفتح البابُ على مصراعَيه**: الدمجُ يُقبل بشرطَين معًا —
     ① أن يكون **مُعلَنًا في القاعدة** (`nav_redirects` النشط: قديم ⇐ جديد)،
     ② وأن يكون **الوارثُ حاضرًا فعلًا في سايدبارِ الدورِ نفسِه** وقتَ القياس.
     فالإعلانُ وحدَه لا يكفي — والغيابُ الفعليُّ يبقى فقدًا يُرسِّب.
   ◆ فالقاعدةُ تصير: «لا يُحذف رابطٌ **إلا إلى وارثٍ مُعلَنٍ حاضر»، وهي القاعدةُ
     نفسُها لا تخفيفٌ لها. */
$mergeInto = array();   // old_file_lc => new_file_lc
$rr = @mysqli_query($conn, "SELECT old_route, new_route FROM nav_redirects WHERE active = 1");
while ($rr && ($x = mysqli_fetch_assoc($rr))) {
    $of = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($x['old_route']))));
    $nf = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($x['new_route']))));
    if ($of !== '' && $nf !== '' && $of !== $nf) { $mergeInto[$of] = $nf; }
}

/* ── الخفضُ إلى تبويبٍ في الأب: بابٌ رابعٌ بالشرطَينِ أنفسِهما (RPR-W03) ─────
   ◆ **الثغرةُ التي يسدُّها** — وقعت فعلًا: `Equipments/equipment_sourcing.php`
     و`Equipments/equipment_documents.php` خُفضا تبويبَين في أبيهما (RPR-W03
     §٤-١-٥: `child of …` ⇒ تبويبٌ لا بندُ قائمة)، فاختفى ملفّاهما من سايدبارِ
     الدورِ 3. **والوصولُ لم ينقص حرفًا**: شريطُ رحلةِ الكيانِ في
     `Equipments/equipments.php` يحملهما، **وهو حاضرٌ في سايدبارِ الدورِ نفسِه**.
   ◆ **ولا يُصرَّح بـ`nav_redirects`**: ذاك جدولُ تحويلٍ **حيٍّ** يقرؤه
     `includes/route_redirect.php` — فصفٌّ فيه يجعل فتحَ التبويبِ مباشرةً يقفز
     إلى الأب، أي **يقتل التبويبَ الذي يُفترض أن يحفظه**. فالإعلانُ في سجلِّ
     الإخفاءِ (`gov_nav_hidden_log`) والوراثةُ من سجلِّ تبويباتِ الكيانات.
   ◆ **والشرطانِ هما هما**: ① إعلانٌ في القاعدةِ لهذا الدورِ بعينِه
     (`doc_code='RPR-W03'` و`reachable='TAB_IN_PARENT'` — والوسمُ الثاني يعني
     أنَّ الدورَ كان **يرى** البندَ مُصيَّرًا، فالصفُّ النشِطُ غيرُ المُصيَّرِ
     يُوسَم `NOT_RENDERED` ولا يُحتَجُّ به) · ② والأبُ **حاضرٌ فعلًا في سايدبارِ
     الدورِ نفسِه** وقتَ القياس. فالإعلانُ وحدَه لا يكفي. */
$tabDemoted = array();   // role_id => [file_lc => true]
$td = @mysqli_query($conn, "SELECT role_id, LOWER(route) rt FROM gov_nav_hidden_log
                             WHERE doc_code = 'RPR-W03' AND reachable = 'TAB_IN_PARENT'");
while ($td && ($x = mysqli_fetch_assoc($td))) {
    $f = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($x['rt']))));
    if ($f !== '') { $tabDemoted[(int) $x['role_id']][$f] = true; }
}
/* والأبُ يُقرأ من مصدرِه الواحد `includes/entity_tabs.php` لا يُنسخ هنا */
$tabParent = array();    // child_file_lc => parent_file_lc
if (is_file(__DIR__ . '/../includes/entity_tabs.php')) {
    require_once __DIR__ . '/../includes/entity_tabs.php';
    if (function_exists('ems_entity_tabs_registry')) {
        foreach (ems_entity_tabs_registry() as $ent) {
            $parent = '';
            foreach ($ent['tabs'] as $route) {
                if ($route === '') { continue; }
                if ($parent === '') { $parent = strtolower($route); continue; }
                $tabParent[strtolower($route)] = $parent;
            }
        }
    }
}

/* ══ سجلُّ الإزالةِ المصرَّحةِ بعزلِ الإدارات — بالدورِ لا بالمساحةِ اسمًا ══════
   ◆ يُقرأ من `gov_space_appearances` عبرَ `gov_space_roles`، فالربطُ **مقيسٌ**
     (تقاطعُ المساراتِ المُصيَّرة) لا مطابقةَ أسماءٍ عربيةٍ تختلف بحرف.
   ◆ **ولا يُصرَّح إلا بالمساحةِ بعينِها**: ما أُزيل من دورٍ ولم يُسجَّل ممنوعًا
     في مساحتِه يبقى فقدًا يُرسِّب — فلا يصير هذا البابُ غطاءً لكلِّ اختفاء. */
$forbiddenBySpace = array();
$fq = @mysqli_query($conn, "SELECT r.role_id, LOWER(a.route) rt
                              FROM gov_space_roles r
                              JOIN gov_space_appearances a ON a.space_ar = r.space_ar
                             WHERE a.cls = 'FORBIDDEN'");
while ($fq && ($x = mysqli_fetch_assoc($fq))) {
    $forbiddenBySpace[(int) $x['role_id']][$x['rt']] = 1;
}

/* ══ سجلُّ النقلِ إلى بلاطاتِ «الوصول السريع» ═══════════════════════════════
   ◆ **عذرٌ ثانٍ للإزالةِ المصرَّحة، أضيقُ من العزل**: بندٌ نُقل من القائمةِ
     إلى بلاطاتِ لوحةِ التحكمِ **لئلّا يظهر في القائمتَين معًا** لم يُفقَد —
     تغيَّر موضعُه. وقيدُه في `gov_nav_hidden_log` بعذرِ `QUICK_TILE`.
   ◆ **ولم يُستعمل `FORBIDDEN` لهذا**: تصنيفُ المساحةِ يعني «ليست لهذه
     الإدارة»، وهو يُخرج المسارَ من **بحثِ الإدارةِ وتصديرِ إكسل**
     (`excel.php:102`) ومن حارسِ الروابطِ المباشرة. فوسمُ شاشةٍ يوميةٍ
     مملوكةٍ بهذا يمنع صاحبَها من تصديرِها — **علاجٌ يُعطِب ما يداوي**.
   ◆ **والشرطانِ معًا لا أحدُهما**: قيدٌ بعذرِ `QUICK_TILE` **و**صفُّ
     `modules` حيٌّ (`is_quick=1 AND is_link=1`) لصاحبِ الدور. فقيدٌ بلا
     بلاطةٍ **فقدٌ يُرسِّب** — وإلا صار السجلُّ غطاءً لكلِّ اختفاء. */
$quickMoved = array();
$mq = @mysqli_query($conn, "SELECT h.role_id, LOWER(h.route) rt
                              FROM gov_nav_hidden_log h
                              JOIN modules m ON LOWER(m.code) = LOWER(h.route)
                                            AND m.owner_role_id = h.role_id
                                            AND m.is_quick = 1 AND m.is_link = 1
                             WHERE h.reachable = 'QUICK_TILE'");
while ($mq && ($x = mysqli_fetch_assoc($mq))) {
    $quickMoved[(int) $x['role_id']][$x['rt']] = 1;
}

/** هويةُ البند: (الملفُّ الأمُّ صغيرًا) + الاسمُ المعروض */
function upc_key($href, $label)
{
    $f = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($href))));
    return $f . '||' . trim($label);
}

/* ── الأساسُ القبليُّ الملتزم ── */
$pre = array();
$lines = file($base);
array_shift($lines);
foreach ($lines as $l) {
    $c = explode("\t", rtrim($l, "\n"));
    if (count($c) < 8) { continue; }
    $rid = (int) $c[0];
    $k = upc_key($c[6], $c[5]);
    $pre[$rid][$k] = isset($pre[$rid][$k]) ? $pre[$rid][$k] + 1 : 1;
}

/* ── الحاضرُ: التصييرُ الحيُّ نفسُه ── */
$fails = 0; $totPre = 0; $totNow = 0; $renamed = 0; $merged = 0; $resurfaced = 0; $absorbed = 0; $isolated = 0; $recon = array();
echo "════ مصفوفةُ الحفظِ — بنودُ السايدبارِ الظاهرةُ قبلًا وبعدًا ════\n";
foreach (uxp_root_roles() as $rid) {
    if (isset($archivedRoles[$rid])) {
        $a = $archivedRoles[$rid];
        echo "  ◆ دور {$rid}: مؤرشَفٌ بإثبات ({$a['proof']}) — {$a['n']} صفًّا محجورًا حتى {$a['purge']}"
           . " · يُستثنى من المقارنةِ ولا يُعدُّ فقدًا\n";
        continue;
    }
    $now = array();
    foreach (uxp_render_role($conn, $rid) as $p) {
        $k = upc_key($p['href'], $p['label']);
        $now[$k] = isset($now[$k]) ? $now[$k] + 1 : 1;
    }
    /* ══ البابُ الخامس: مُنِع بقالبٍ نافذٍ — من المصدرِ الذي يقرؤه المُصيِّرُ ══
       ◆ طبقةُ قوالبِ GOV-AUTH-01 سجلٌّ محكومٌ («لا شاشةَ خارجَ القالب» — قرارُ
         المالك 2026-08-17)، والمُصيِّرُ صار يحجب رابطًا بابُه مردودٌ بالقالبِ
         (RPR-03 §٦). فغيابُه هنا **إنفاذُ سجلٍّ لا فقدٌ صامت** — ويُقاس من
         حالةِ قالبِ مستخدمِ القياسِ نفسِه (الجلسةُ بعد التصييرِ تحمله) لا من
         قائمةٍ في الكود. ومتى ردَّ المالكُ البندَ للقالبِ (OA-TEMPLATE-15)
         عاد الرابطُ وخرج من هذا البابِ وحدَه. */
    $tplDeniedHere = array();
    if (function_exists('unifiedNavTemplateState')) {
        $__tplR = unifiedNavTemplateState($conn);
        if (!empty($__tplR['covered'])) {
            $__tq = @mysqli_query($conn, "SELECT LOWER(n.route) rt, m.code
                                            FROM nav_items n JOIN modules m ON m.id = n.module_id
                                           WHERE n.role_id = " . (int) $rid . " AND n.active = 1
                                             AND n.permission_code IS NOT NULL AND n.permission_code <> ''");
            while ($__tq && ($__tx = mysqli_fetch_assoc($__tq))) {
                if (!isset($__tplR['allowed'][(string) $__tx['code']])) {
                    $f0 = strtolower(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', trim($__tx['rt']))));
                    if ($f0 !== '') { $tplDeniedHere[$f0] = true; }
                }
            }
        }
    }
    $p = isset($pre[$rid]) ? $pre[$rid] : array();
    $cntPre = array_sum($p); $cntNow = array_sum($now);
    $totPre += $cntPre; $totNow += $cntNow;
    /* التجميعُ بالملفِّ الأم: الدمجُ لا يقع إلا بين توائمِ الملفِّ الواحد */
    $preF = array(); $nowF = array();
    foreach ($p as $k => $c)   { list($f, $l) = explode('||', $k, 2); $preF[$f]['labels'][$l] = ($preF[$f]['labels'][$l] ?? 0) + $c; $preF[$f]['n'] = ($preF[$f]['n'] ?? 0) + $c; }
    foreach ($now as $k => $c) { list($f, $l) = explode('||', $k, 2); $nowF[$f]['labels'][$l] = ($nowF[$f]['labels'][$l] ?? 0) + $c; $nowF[$f]['n'] = ($nowF[$f]['n'] ?? 0) + $c; }
    $missing = array(); $renamedHere = 0; $mergedHere = 0; $absorbedHere = 0; $isolatedHere = 0; $quickHere = 0; $tplHere = 0;
    foreach ($preF as $f => $P) {
        /* ملفٌّ اختفى من الدور = فقدٌ صريح — إلا أن يكون **ذاب في وارثٍ مُعلَنٍ
           حاضرٍ في الدورِ نفسِه**: الإعلانُ من nav_redirects والحضورُ مقيسٌ الآن. */
        /* ◆ **ووارثٌ انتقل إلى بلاطةٍ ما يزال وارثًا حاضرًا**: الذوبانُ يُقاس
             بوجودِ الوارثِ لا بموضعِه. ونقلُ الوارثِ إلى لوحةِ التحكمِ كان
             **يُيتِّم ورثتَه دفعةً واحدة** فتُقرأ ستةُ ملفاتٍ مفقودةً وهي
             سليمة — عطبٌ سببُه المقياسُ لا التغيير. */
        if (!isset($nowF[$f]) && isset($mergeInto[$f])
            && (isset($nowF[$mergeInto[$f]]) || isset($quickMoved[$rid][mb_strtolower($mergeInto[$f])]))) {
            $absorbedHere += $P['n'];
            continue;
        }
        /* ══ عزلُ الإدارات — إزالةٌ **مصرَّحةٌ بسجلٍّ** لا فقدٌ صامت ══════════
           ◆ نصُّ المالك: «**صفرُ فقد — والإزالةُ من مساحةٍ ليست حذفًا من النظام**».
             فالمسارُ المصنَّفُ `FORBIDDEN` **لهذه المساحةِ بعينِها** أُزيل ظهورُه
             بقرارٍ مسجَّلٍ في `gov_space_appearances` بشاهدِ عقدةِ الشجرةِ التي
             حسمَته — وهو باقٍ في النظامِ ومفتوحٌ في مساحتِه المالكة.
           ◆ **والتصريحُ مقيَّدٌ بالمساحةِ لا مطلق**: ما أُزيل من مساحةٍ ولم يُسجَّل
             ممنوعًا فيها **يبقى فقدًا غيرَ مصرَّحٍ ويُرسِّب** — فلا يصير هذا البابُ
             غطاءً لكلِّ اختفاء. */
        /* خفضٌ إلى تبويبٍ في أبٍ حاضرٍ في الدورِ نفسِه — البابُ الرابع (RPR-W03) */
        if (!isset($nowF[$f]) && isset($tabDemoted[$rid][mb_strtolower($f)])
            && isset($tabParent[mb_strtolower($f)])
            && isset($nowF[$tabParent[mb_strtolower($f)]])) {
            $absorbedHere += $P['n'];
            continue;
        }
        if (!isset($nowF[$f]) && isset($forbiddenBySpace[$rid][mb_strtolower($f)])) {
            $isolatedHere += $P['n'];
            continue;
        }
        /* مُنِع بقالبٍ نافذٍ — إنفاذُ سجلِّ GOV-AUTH-01 (البابُ الخامس أعلاه) */
        if (!isset($nowF[$f]) && isset($tplDeniedHere[mb_strtolower($f)])) {
            $isolatedHere += $P['n'];   /* المقامُ نفسُه: إزالةٌ مصرَّحةٌ بسجلٍّ */
            $tplHere      += $P['n'];
            continue;
        }
        if (!isset($nowF[$f]) && isset($quickMoved[$rid][mb_strtolower($f)])) {
            $isolatedHere += $P['n'];   /* يُحسب في المقامِ نفسِه: إزالةٌ مصرَّحة */
            $quickHere    += $P['n'];
            continue;
        }
        if (!isset($nowF[$f])) { $missing[] = 'الملفُّ كلُّه: ' . $f; continue; }
        $N = $nowF[$f];
        $canonName = isset($canon[$f]) ? $canon[$f]['canon'] : null;
        foreach ($P['labels'] as $l => $c) {
            if (isset($N['labels'][$l])) { continue; }                                     /* ① الاسمُ باقٍ */
            if ($canonName !== null && isset($N['labels'][$canonName])) {                  /* ② اسمٌ حلَّ محلَّه معياريُّه المعتمَد */
                $renamedHere += $c; continue;
            }
            $missing[] = '«' . $l . '» (' . $f . ')';                                      /* ③ اسمٌ ضاع بلا بديلٍ مسجَّل */
        }
        if ($N['n'] < $P['n']) { $mergedHere += ($P['n'] - $N['n']); }                     /* توائمُ باسمٍ واحدٍ اندمجت — يُبلَّغ */
    }
    $added = array();
    foreach ($nowF as $f => $N) {
        foreach ($N['labels'] as $l => $c) {
            $had = isset($preF[$f]['labels'][$l]);
            $canonName = isset($canon[$f]) ? $canon[$f]['canon'] : null;
            if (!$had && !($canonName !== null && $l === $canonName)) { $added[] = '«' . $l . '» (' . $f . ')'; }
            elseif (!$had && $canonName !== null && $l === $canonName && !isset($preF[$f])) { $added[] = '«' . $l . '» (' . $f . ')'; }
        }
    }
    $renamed += $renamedHere; $merged += $mergedHere; $resurfaced += count($added); $isolated += $isolatedHere;
    $recon[$rid] = array("pre"=>$cntPre,"now"=>$cntNow,"abs"=>$absorbedHere,"mrg"=>$mergedHere,"iso"=>$isolatedHere,"add"=>count($added));
    $absorbed += $absorbedHere;
    $ok = empty($missing);
    if (!$ok) { $fails++; }
    echo ($ok ? '  ✔' : '  ✗') . " دور {$rid}: قبل={$cntPre} · بعد={$cntNow}"
        . ($absorbedHere ? " · ذاب في وارثٍ مُعلَنٍ حاضر={$absorbedHere}" : '')
        . ($isolatedHere ? " · **أُزيل بعزلٍ مصرَّحٍ={$isolatedHere}**" : '')
        . ($tplHere ? " · **مُنِع بقالبٍ نافذٍ={$tplHere}**" : '')
        . ($quickHere ? " · **نُقل إلى بلاطةٍ={$quickHere}**" : '')
        . ($renamedHere ? " · أُعيدت تسميتُه بالسجل={$renamedHere}" : '')
        . ($mergedHere ? " · توأمٌ مندمجٌ باسمٍ واحد={$mergedHere}" : '')
        . ($missing ? ' · ناقص: ' . implode(' ، ', array_slice($missing, 0, 6)) : '')
        . ($added ? ' · جديدُ الظهور: ' . implode(' ، ', array_slice($added, 0, 4)) : '') . "\n";
}
echo "الإجمالي: قبل={$totPre} · بعد={$totNow} · أُعيدت تسميتُه بالسجل={$renamed} · ذاب في وارثٍ مُعلَن={$absorbed} · توأمٌ مندمج={$merged} · **أُزيل بعزلٍ مصرَّح={$isolated}** · جديدُ الظهور={$resurfaced}\n";
/* ◆ والفرقُ يُصالَح حسابيًّا ولا يُترك للقارئ: قبل − بعد يجب أن يساوي مجموعَ
     المصرَّحاتِ الثلاثِ ناقصَ ما عاد للظهور. **وفارقُ مقامٍ بلا سطرِ تصالحٍ
     يطابق حسابيًّا لا يُقبل** — وهو شرطُ المواصفةِ نفسِها (٢٠-٣). */
$expected = $totPre - $absorbed - $merged - $isolated + $resurfaced;
printf("مصالحةُ المقام: %d − (ذاب %d + مندمج %d + معزول %d) + جديد %d = %d · والمقيسُ بعدًا %d ⇒ %s\n",
    $totPre, $absorbed, $merged, $isolated, $resurfaced, $expected, $totNow,
    ($expected === $totNow ? '✔ يطابق' : '✘ فرقٌ ' . ($totNow - $expected)));
if ($expected !== $totNow) {
    /* ◆ **ولا يُترك الفرقُ رقمًا عامًّا**: يُنسب إلى دورِه بعينِه، فالفرقُ المنسوبُ
         يُفحَص والفرقُ المعلَّقُ في الهواءِ يُتجاهَل. */
    echo "  ◆ الفرقُ منسوبًا إلى أدوارِه:\n";
    foreach ($recon as $rid => $r) {
        $exp = $r['pre'] - $r['abs'] - $r['mrg'] - $r['iso'] + $r['add'];
        if ($exp !== $r['now']) {
            printf("    · دور %-3d متوقَّع=%d مقيس=%d فرق=%+d\n", $rid, $exp, $r['now'], $r['now'] - $exp);
        }
    }
}
if ($fails === 0) { echo "✔ صفرُ فقدٍ غيرِ مصرَّح — كلُّ بندٍ قبليٍّ موجودٌ بهويتِه أو باسمِه المعياريِّ المسجَّل\n"; exit(0); }
echo "✗ {$fails} دورًا فيه نقصٌ غيرُ مصرَّح — " . ($GATE ? 'التسليمُ مرسَّب' : 'راجِع قبل أيِّ تسليم') . "\n";
exit($GATE ? 1 : 0);
