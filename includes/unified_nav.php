<?php
require_once __DIR__ . '/permissions_helper.php'; // FINAL_CLOSE ⑦ — جملة الظهور من المصدر الواحد
/**
 * المصيِّر الموحّد للسايدبار — بوابة UX-02 §9-④ · UX-01 §10.3
 * ─────────────────────────────────────────────────────────────────────────
 * مصدرٌ واحد (nav_items) بدل المصادر الخمسة، على قاعدة المالك (2026-07-26):
 *   «الدورُ يحدد الروابطَ التي تتبع له، والصلاحيةُ تحدد أتظهر الصفحةُ أم لا.»
 * أي: الظهور = صفٌّ تابعٌ للدور في nav_items (active=1) **و** can_view=1
 * (أو permission_code NULL للثوابت بلا فحص — سلوكُ اليوم حرفيًّا).
 *
 * التشغيل مزدوجٌ بعلَمٍ لكل دور (UX-01 §10.4-②): EMS_NAV_UNIFIED_ROLES في
 * .env قائمةُ أدوارٍ بفواصل؛ الدورُ المذكور يرى الأبواب الستة من المصدر
 * الموحّد، وسائرُ الأدوار على مصادرها القديمة حرفيًّا — ورجوعُ أي دورٍ
 * بحذف رقمه من العلم، بلا نشر كود.
 *
 * الأبواب حاوياتٌ قابلةٌ للطيّ بهيكل مجموعات الروابط القائم نفسه
 * (زرُّ رأسٍ لا رابط · data-group-key لذاكرة الحالة · شارةُ مجموعِ الأبناء)
 * فترث CSS ems-nav-groups وسلوكَ الطيّ والتوهج الذهبي بلا تعديل. ومجموعاتُ
 * link_groups تبقى داخل الباب فواصلَ عنونةٍ (لا طيًّا متداخلًا — قرارُ
 * تبسيطٍ مقصود للنسخة الأولى).
 */

require_once __DIR__ . '/dynamic_nav.php';
require_once __DIR__ . '/nav_icon_map.php';
require_once __DIR__ . '/nav_groups.php';

/** الأبواب الثمانية (UX-00 §6 معدَّلًا بقرار DEC-01 ② الصريح — لا تنفيذ صامتًا).
 * HOME هو باب «① الرئيسية» الدستوري: عنصرٌ واحدٌ لكل دورٍ يفتح لوحتَه
 * (§7) — عامةً كانت أو مخصصةً — ويُطبع مسطّحًا قبل الأبواب لا داخل مجموعةٍ
 * مطوية، فيكون أولَ ما يُرى. صفوفُه في nav_items منذ 2026-07-27.
 * GOV خلف صلاحية حوكمةٍ إدارية · FIN خلف **بوابة المجال المقيَّد** (FIN-01
 * §1.1): مستويا سريةٍ مختلفان فلا يُدمجان تحت سقفٍ واحد — ومن لا صلاحيةَ
 * له **لا يُصيَّر له البابُ أصلًا** لا معطَّلًا ولا مخفيَّ المحتوى. */
function unifiedNavDoors() {
    return array(
        'HOME'  => array('name' => 'الرئيسية',            'icon' => 'fa fa-house', 'flat' => true),
        'DAILY' => array('name' => 'العمل اليومي',        'icon' => 'fa fa-briefcase'),
        'APPR'  => array('name' => 'المتابعة والموافقات', 'icon' => 'fa fa-clipboard-check'),
        'REC'   => array('name' => 'السجلات الرئيسية',    'icon' => 'fa fa-database'),
        'REP'   => array('name' => 'التقارير والتحليلات', 'icon' => 'fa fa-chart-pie'),
        'GOV'   => array('name' => 'الحوكمة',             'icon' => 'fa fa-landmark'),
        'FIN'   => array('name' => 'التمويل',             'icon' => 'fa fa-hand-holding-dollar'),
        'SET'   => array('name' => 'الإعدادات والتدقيق',  'icon' => 'fa fa-cog'),
        /* RISK — بابٌ تاسعٌ سُجِّل 2026-08-10 (INJ-0059). لم يكن مسجَّلًا وله
           80 صفًّا في 18 دورًا: كانت تُصيَّر لأن لها `stage_no` فتسبق حلقةَ
           الأبواب، وأيُّ صفٍّ منها يفقد مرحلتَه كان يسقط **صامتًا**. والتسجيلُ
           يجعل عقدَ المُصيِّرِ مطابقًا للواقع. وطيُّها تحت GOV مرفوضٌ: بابُ
           الحوكمةِ مزدحمٌ أصلًا، ودفنُ المخاطرِ فيه يُخفي مجالًا أولَ الدرجة. */
        'RISK'  => array('name' => 'المخاطر',             'icon' => 'fa fa-triangle-exclamation'),
    );
}

/**
 * هل الدور على المصدر الموحّد؟ (علم التشغيل المزدوج)
 * @param mixed $roleId  دور الجلسة
 * @param string|null $csv  تجاوزٌ اختباري؛ null = من البيئة
 */
function unifiedNavEnabled($roleId, $csv = null) {
    if ($csv === null) {
        $csv = function_exists('ems_env') ? (string) ems_env('EMS_NAV_UNIFIED_ROLES', '') : '';
    }
    if (trim($csv) === '') { return false; }
    $roles = array_map('trim', explode(',', $csv));
    return in_array(strval(intval($roleId)), $roles, true);
}

/**
 * حالةُ قالبِ المستخدمِ الحاليِّ (GOV-AUTH-01) — للتصييرِ لا للحراسة.
 *
 * القارئُ الفعليُّ في طبقةِ المصدرِ `permissions_helper.php`
 * (`ems_template_nav_state`) — فالمحرّكُ لا يقرأ جدولَ صلاحيةٍ بنفسِه،
 * وهذا الغلافُ يضمن تحميلَ المُساعِدِ ويُبقي اسمَ النداءِ القائم.
 *
 * @return array{covered:bool, allowed:array<string,1>}
 */
function unifiedNavTemplateState($conn) {
    if (!function_exists('ems_template_nav_state')) {
        require_once __DIR__ . '/permissions_helper.php';
    }
    if (!function_exists('ems_template_nav_state')) {
        return array('covered' => false, 'allowed' => array());
    }
    return ems_template_nav_state($conn);
}

/**
 * عناصر الدور الظاهرة: تبعيةٌ (active=1) × صلاحية (can_view=1 أو بلا فحص).
 * استعلامٌ واحد — الرابطُ الميت مستبعدٌ بنيويًّا لا برأي الشاشة.
 */
function getUnifiedNavItems($conn, $roleId) {
    $roleId = intval($roleId);
    /* ── INJ-0491 · مصدرٌ واحدٌ لترتيبِ الأبواب ──────────────────────────────
       كان الترتيبُ سلسلةً مكتوبةً في SQL بثمانيةِ أبوابٍ بينما `unifiedNavDoors()`
       تُعرّف تسعة — فبابُ `RISK` خارجَ السلسلة. و`FIELD` تُعيد **صفرًا** لما لا
       تجده، والصفرُ يسبق الواحد: فأيُّ صفِّ RISK يخرج من مجموعةٍ مرحليةٍ يقفز
       **قبل الرئيسية**. (واليومَ لا يقع الأثرُ لأنَّ صفوفَ RISK الثمانين كلَّها
       داخلَ مجموعاتٍ مرحلية، فلا تبلغ حلقةَ الأبواب — عيبٌ نائمٌ لا معدوم.)
       ويُشتقُّ الترتيبُ الآن من التعريفِ نفسِه: مصدرٌ واحدٌ لا اثنان يتفرَّقان. */
    $doorOrder = implode(',', array_map(function ($d) { return "'" . $d . "'"; },
        array_keys(unifiedNavDoors())));
    $sql = "SELECT n.door, n.group_id, n.label_ar, n.route, n.icon, n.sort_order,
                   n.counter_source, n.permission_code, m.code AS module_code,
                   g.name AS group_name,
                   g.stage_no, g.stage_title, g.stage_desc, g.display_order AS group_order
            FROM nav_items n
            LEFT JOIN modules m ON m.id = n.module_id
            LEFT JOIN link_groups g ON g.id = n.group_id AND g.is_active = 1
            WHERE n.role_id = {$roleId} AND n.active = 1
              AND (
                    n.permission_code IS NULL
                    OR " . perm_nav_view_exists_sql('n') . "
                  )
            ORDER BY FIELD(n.door,{$doorOrder}), n.sort_order, n.id";
    $items = array();
    $res = mysqli_query($conn, $sql);
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { $items[] = $row; } }

    /* ── RPR-03 §٦ — طبقةُ قوالبِ GOV-AUTH-01 في قرارِ الظهورِ نفسِه ─────────
       المغطّى بقالبٍ نافذٍ يُحكَم بقالبِه حصرًا (دلالةُ get_module_permissions
       حرفًا: «لا شاشةَ خارجَ القالب») — فرابطٌ يمنعه القالبُ لا يُصيَّر أصلًا،
       بدل أن يظهرَ ويُردَّ بابُه. استعلامان مجمَّعان للمستخدمِ لا نداءٌ لكلِّ
       رابطٍ — وغيرُ المغطّى على المنحِ القائمِ كما هو. */
    $tpl = unifiedNavTemplateState($conn);
    if ($tpl['covered']) {
        $items = array_values(array_filter($items, function ($it) use ($tpl) {
            $pc = isset($it['permission_code']) ? trim((string) $it['permission_code']) : '';
            if ($pc === '') { return true; }               /* بلا فحصٍ — حارسُه في وجهتِه */
            $code = (string) ($it['module_code'] ?? '');
            return $code !== '' && isset($tpl['allowed'][$code]);
        }));
    }

    // H-20: سايدبارُ المشرف الخارجي «أضيقُ عمدًا» (UX-05 §4) — الجلسةُ
    // المقيَّدةُ بمورد تُرشَّح عناصرُها لقائمة البوابة (إخفاءٌ لا حذفُ منح).
    if (isset($_SESSION['user'])) {
        require_once dirname(__DIR__) . '/app/Services/Portal/SupplierPortalGuard.php';
        $items = \App\Services\Portal\SupplierPortalGuard::filterNavItems($_SESSION['user'], $items);
    }

    // DEC-01 ②: باب التمويل خلف بوابة المجال المقيَّد (N-21 · FIN-01 §1.1) —
    // صلاحية can_view لا تكفي: بلا منحةٍ فرديةٍ نافذة **لا يُصيَّر الباب أصلًا**
    // (fail-closed كالبوابة نفسها). السوبر (-1) خارج الترشيح.
    // NAV-09: روابطُ التمويل تولَّد بمسارها لا ببابها — فالبوابةُ على البادئة
    // Financing/ لكل الأدوار (والوثيقةُ تعلن العارضين، والمنحةُ الفرديةُ تكشف)
    $hasFin = false;
    foreach ($items as $it) {
        if ($it['door'] === 'FIN' || strpos($it['route'], 'Financing/') === 0) { $hasFin = true; break; }
    }
    if ($hasFin && isset($_SESSION['user']) && strval($_SESSION['user']['role'] ?? '') !== '-1') {
        require_once dirname(__DIR__) . '/app/Core/OwnershipDomainGuard.php';
        $uid = intval($_SESSION['user']['id'] ?? 0);
        $co = intval($_SESSION['user']['company_id'] ?? 0);
        $granted = false;
        foreach (array(\App\Core\OwnershipDomainGuard::PERM_OWNER,
                       \App\Core\OwnershipDomainGuard::PERM_TERMS,
                       \App\Core\OwnershipDomainGuard::PERM_VALUE) as $p) {
            if (\App\Core\OwnershipDomainGuard::hasGrant($conn, $co, $uid, $p)) { $granted = true; break; }
        }
        if (!$granted) {
            $items = array_values(array_filter($items, function ($it) {
                return $it['door'] !== 'FIN' && strpos($it['route'], 'Financing/') !== 0;
            }));
        }
    }

    /* ══ عزلُ الإدارات (ثامنًا-⑦) — الإزالةُ من **المساحةِ الخاطئةِ وحدَها** ══
       ◆ نصُّ المالك: «`user + active_scope + route` لا `user + route`… **ومنعُها
         عالميًّا يكسر صلاحيةً مشروعة**». فالترشيحُ هنا يسأل عن **هذه المساحةِ**
         لا عن المستخدمِ مطلقًا: الشاشةُ الممنوعةُ في مساحةٍ تظهر في مساحتِها
         المالكةِ كاملةً بأفعالِها.
       ◆ **وموضعُه هنا قصدًا**: `getUnifiedNavItems` هي **البابُ الوحيدُ** لعناصرِ
         التنقل، فالإزالةُ فعلٌ واحدٌ في موضعٍ واحد. ولو نُثر الترشيحُ في المصيِّرات
         لتفرّق ونجا مسارٌ من بابٍ ثانٍ.
       ◆ **ولا يُلغى مسارٌ ولا نقطةُ نهايةٍ ولا حدث**: هذا **إخفاءُ ظهورٍ** في
         مساحةٍ بعينِها؛ والمسارُ يبقى مفتوحًا لمالكِه، ويبقى مقصدًا لفعلٍ أو
         مهمةٍ أو رابطٍ من شاشةٍ أخرى. **صفرُ فقد — والإزالةُ من مساحةٍ ليست
         حذفًا من النظام.**
       ◆ **والغيابُ ليس منعًا**: ما لا صفَّ له في اللقطةِ يمرُّ — فبوابةٌ تمنع ما
         لا تعرفه تكسر النظامَ صامتةً عند أولِ شاشةٍ جديدة. */
    if (isset($_SESSION['user']) && strval(isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '') !== '-1') {
        $scopeFile = dirname(__DIR__) . '/includes/space_scope.php';
        if (is_file($scopeFile)) {
            require_once $scopeFile;
            $scope = ems_active_scope();
            if ($scope !== '') {
                $forb = ems_scope_forbidden_set($scope);
                if (!empty($forb)) {
                    $items = array_values(array_filter($items, function ($it) use ($forb) {
                        $r = ltrim(preg_replace('/[?#].*$/', '', (string) $it['route']), '/');
                        return !isset($forb[mb_strtolower($r)]);
                    }));
                }
            }
        }
    }

    return $items;
}

/**
 * طباعة بابٍ واحد بهيكل nav-group القائم + فواصلِ مجموعاتٍ داخلية.
 * الشارةُ على الرأس مجموعُ شارات الأبناء (وإلا اختفى المعلَّق داخل بابٍ مطوي).
 */
function printUnifiedNavDoor($doorKey, $doorMeta, $items, $basePrefix = '../', $badges = array()) {
    if (empty($items)) { return; }
    $key  = 'door-' . strtolower($doorKey);
    $name = htmlspecialchars($doorMeta['name'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars($doorMeta['icon'], ENT_QUOTES, 'UTF-8');

    $total = 0;
    foreach ($items as $it) {
        if (isset($badges[$it['route']])) { $total += intval($badges[$it['route']]); }
    }
    $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';

    /* ══ الترتيبُ: الروابطُ أولًا ثم الغلاف — حارسُ الغلافِ الفارغ ═══════════
       ◆ `printNavLinkItem` قد تُسقط الرابطَ (كابحُ مساحةِ العمل أو حارسُ
         التكرار)، فتُبنى المجموعةُ من **الناجي** وحدَه: فلا عنوانَ فرعيٌّ بلا
         روابطَ تحته · ولا عدَّ «المزيد» يعِد بما لا يوجد · ولا غلافَ لبابٍ
         خلا من روابطِه. **ولا يُخفى رابطٌ ولا يُلغى مسار.** */
    $byGroup = array();
    foreach ($items as $it) {
        $g = isset($it['group_name']) && $it['group_name'] !== null ? $it['group_name'] : '';
        $byGroup[$g][] = $it;
    }
    static $moreSeq = 0;
    ob_start();
    foreach ($byGroup as $gname => $gItems) {
        $kept = array();
        foreach ($gItems as $it) {
            ob_start();
            printNavLinkItem(array(
                'code' => $it['route'],
                'name' => $it['label_ar'],
                'icon' => $it['icon'],
            ), $basePrefix, $badges);
            $one = ob_get_clean();
            if (ems_nav_group_has_link($one)) { $kept[] = array('it' => $it, 'html' => $one); }
        }
        if (empty($kept)) { continue; }
        // UI-DEF-05: مجموعةٌ برابطٍ واحدٍ يحمل اسمَها نفسَه كانت تطبع النصَّ مرتين
        // (رأسًا ثم رابطًا) فتُقرأ تكرارًا — الرأسُ يسقط والرابطُ يبقى.
        $soloSameName = (count($kept) === 1 && trim((string) $kept[0]['it']['label_ar']) === trim((string) $gname));
        if ($gname !== '' && !$soloSameName) {
            echo '<li class="nav-subhead" aria-hidden="true"><span>'
               . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
        }
        // NAV-01 v6 §4 (update0007-ب): التكدّسُ يُدار بأدوات التعقيد — المجموعةُ
        // الكثيفةُ (> 12) تعرض أولَها ويطوي «المزيدُ» بقيتَها قابلةً للفتح،
        // «فلا تظهر المستوياتُ كلُّها دفعةً واحدة» — بلا حذفِ رابطٍ ولا تغييرِ بنية.
        $dense = count($kept) > 12;
        foreach ($kept as $idx => $k) {
            if ($dense && $idx === 7) {
                $moreSeq++;
                $rest = count($kept) - 7;
                echo '<li class="nav-more-toggle"><button type="button" class="nav-group-head" '
                   . 'style="font-size:.85em;opacity:.8" '
                   . 'onclick="var m=document.getElementById(\'navmore-' . $moreSeq . '\');'
                   . 'var open=m.style.display!==\'none\';m.style.display=open?\'none\':\'block\';'
                   . 'this.querySelector(\'span\').textContent=open?\'المزيد (' . $rest . ') ▾\':\'أقل ▴\';">'
                   . '<span>المزيد (' . $rest . ') ▾</span></button></li>' . "\n";
                echo '<li><ul id="navmore-' . $moreSeq . '" style="display:none;list-style:none;padding:0;margin:0">' . "\n";
            }
            echo $k['html'];
        }
        if ($dense) { echo '</ul></li>' . "\n"; }
    }
    $body = ob_get_clean();
    if (!ems_nav_group_has_link($body)) { return; }

    echo '<li class="nav-group" data-group-key="' . $key . '">' . "\n";
    echo '  <button type="button" class="nav-group-head" aria-expanded="false" aria-controls="navgrp-' . $key . '" aria-label="' . $name . '" title="' . $name . '">'
       . '<i class="' . $icon . '" aria-hidden="true"></i> <span class="nav-group-name">' . $name . '</span>' . $badge
       . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
    echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";
    echo $body;
    echo '  </ul>' . "\n";
    echo '</li>' . "\n";
}

/**
 * تصيير القائمة الموحّدة كاملةً للدور — بديلُ الكتل الثلاث والروابط الثابتة
 * (عدا المراسلات — ثابتةٌ بقرار المالك؛ و«الرئيسية» انتقلت 2026-07-27 صفًّا
 *  في باب HOME لكل الأدوار الـ23 فحُذف ثابتُها من insidebar.php).
 * الشارات بمفتاح المسار (route) — تُجمع في insidebar من مصادرها القائمة.
 *
 * @param string $afterHome  HTML خامٌّ يُحقن مباشرةً بعد باب HOME — موضعُ
 *   الروابط الثابتة الباقية (المراسلات) كي تبقى «الرئيسية» أولَ ما يُرى
 *   (الدستور §6) دون أن تهبط الثوابتُ إلى ذيل القائمة.
 */
/**
 * وضعُ المراحل (NAV-09 حكم ١٣): المرحلةُ رأسُ طيٍّ والمجموعاتُ عناوينُ داخلها —
 * «المراحلُ ٠ و١ و٢ مفتوحةٌ وما بعدها مطويّ» · و«أخرى» (99) ذيلٌ مطويٌّ للمراجعة.
 */
/* ── INJ-0570 · عنوانُ المرحلةِ وصفيٌّ لا مُرقَّمٌ لفظيًّا ──────────────────────
     ١١٨ عنوانًا من ١٥٣ يبدأ بترتيبٍ لفظيّ («أولًا:» … «سابعًا:»). والرقمُ اللفظيُّ
     يَعِد المستخدمَ بتسلسلٍ متصل — فحين لا يملك دورٌ مرحلةً بعينها يقرأ
     «رابعًا ثمّ سادسًا» فيظنُّ **نقصًا في دورتِه**. ودورُ «إدارة الموقع» (٦)
     مثالُه الحيُّ: ١·٢·٣·٤ ثمّ ٦·٧ — والخامسةُ لا وجودَ لها في دورتِه أصلًا.

     والعلاجُ ليس اختراعَ مرحلةٍ خامسةٍ ولا إعادةَ ترقيمِ الأدوارِ الثلاثين — بل
     **إزالةُ الوعدِ**: العنوانُ يصف الخطوةَ ولا يَعُدُّها. والتسلسلُ الحقيقيُّ
     محفوظٌ في `stage_no` للترتيبِ لا للعرض.

     ويُقشَّر عند العرضِ لا في البيانات: صفٌّ جديدٌ بترقيمٍ لفظيٍّ يُقشَّر هو
     أيضًا — فلا يعود العيبُ بإدخالِ بيانٍ جديد. */
function ems_nav_stage_label($rawTitle, $stageNo) {
    $title = trim((string) $rawTitle);
    if ($title !== '') {
        $ord = 'أولا|أولا|ثانيا|ثانيا|ثالثا|ثالثا|رابعا|رابعا|خامسا|خامسا|'
             . 'سادسا|سادسا|سابعا|سابعا|ثامنا|ثامنا|تاسعا|تاسعا|عاشرا|عاشرا';
        $stripped = preg_replace('~^\s*(?:' . $ord . ')\s*[:：\-–—]\s*~u', '', $title);
        if (is_string($stripped) && trim($stripped) !== '') { $title = trim($stripped); }
    }
    if ($title === '') { $title = ((int) $stageNo === 0) ? 'اللوحة والمساحة' : 'المرحلة ' . (int) $stageNo; }
    return $title;
}

function printStageNav($roleId, array $items, $basePrefix = '../', $badges = array()) {
    $byStage = array();
    foreach ($items as $it) { $byStage[intval($it['stage_no'])][] = $it; }
    ksort($byStage);
    $hdrPrinted = false; // رأسا كل إدارة (قرار المالك 2026-08-03) يُحقنان مرةً في أول مرحلة
    foreach ($byStage as $stageNo => $sItems) {
        usort($sItems, function ($a, $b) {
            return (intval($a['group_order']) - intval($b['group_order']))
                ?: (intval($a['sort_order']) - intval($b['sort_order']));
        });
        $title = ems_nav_stage_label((string) $sItems[0]['stage_title'], $stageNo);
        $key = 'stage-' . $roleId . '-' . $stageNo;
        /* ═══════════════════════════════════════════════════════════════════
         * INJ-0527 — المراحلُ ٠ و١ و٢ تبدأ **مفتوحةً** وما بعدها مطويّ
         * ═══════════════════════════════════════════════════════════════════
         * ◆ **المقيسُ**: كان `$openDefault = false` ثابتًا فتبدأ **كلُّ** مجموعةٍ
         *   مطويّةً — فمديرُ الصيانةِ يفتح النظامَ على ستِّ مجموعاتٍ مغلقةٍ ولا
         *   يرى رابطًا واحدًا حتى يضغط.
         * ◆ **وحكمُ الملفِّ نفسِه يخالف ذلك** (السطر 201): «المراحلُ ٠ و١ و٢
         *   مفتوحةٌ وما بعدها مطويّ». فكان في الملفِّ الواحدِ **قاعدةٌ مكتوبةٌ
         *   وسلوكٌ يناقضها** — والسلوكُ هو ما يراه المستخدم.
         * ◆ ولا يُمَسُّ حفظُ الاختيار: `insidebar.php` يحفظ المفتوحَ في
         *   `localStorage` (OPEN_KEY)، فهذا **الافتراضُ عند أوّلِ زيارةٍ** فقط
         *   ومَن طوى مرحلةً بقيت مطويّةً له.
         * ═══════════════════════════════════════════════════════════════════ */
        $openDefault = ($stageNo >= 0 && $stageNo <= 2);
        $icon = ems_nav_stage_icon($stageNo, $title); // أيقونة معبِّرة من عنوان المرحلة لا واحدة موحّدة

        $total = 0;
        foreach ($sItems as $it) { if (isset($badges[$it['route']])) { $total += intval($badges[$it['route']]); } }
        $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';

        /* ══ جسمُ المرحلةِ يُبنى قبلَ غلافِها — حارسُ الغلافِ الفارغ ═══════════
           `printNavLinkItem` قد تُسقط الرابطَ (كابحُ المساحةِ أو حارسُ التكرار)،
           فتُبنى المرحلةُ من **الناجي** وحدَه: لا عنوانَ فرعيٌّ بلا روابطَ تحته،
           ولا «المزيد» يعِد بما لا يوجد، ولا مرحلةٌ ترأس فراغًا. */
        $byGroup = array();
        foreach ($sItems as $it) { $byGroup[(string) $it['group_name']][] = $it; }
        static $stMoreSeq = 1000;
        ob_start();
        foreach ($byGroup as $gname => $gItems) {
            $kept = array();
            foreach ($gItems as $it) {
                ob_start();
                printNavLinkItem(array('code' => $it['route'], 'name' => $it['label_ar'], 'icon' => $it['icon']), $basePrefix, $badges);
                $one = ob_get_clean();
                if (ems_nav_group_has_link($one)) { $kept[] = array('it' => $it, 'html' => $one); }
            }
            if (empty($kept)) { continue; }
            // UI-DEF-05 نفسه في وضع المراحل: رأسٌ يطابق رابطَه الوحيد لا يُطبع.
            $soloSameName = (count($kept) === 1 && trim((string) $kept[0]['it']['label_ar']) === trim((string) $gname));
            if ($gname !== '' && count($byGroup) > 1 && !$soloSameName) {
                echo '<li class="nav-subhead" aria-hidden="true"><span>'
                   . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
            }
            $dense = count($kept) > 12; // أداةُ التكدس نفسُها (NAV-01 §4)
            foreach ($kept as $idx => $k) {
                if ($dense && $idx === 7) {
                    $stMoreSeq++;
                    $rest = count($kept) - 7;
                    echo '<li class="nav-more-toggle"><button type="button" class="nav-group-head" '
                       . 'style="font-size:.85em;opacity:.8" '
                       . 'onclick="var m=document.getElementById(\'navmore-' . $stMoreSeq . '\');'
                       . 'var open=m.style.display!==\'none\';m.style.display=open?\'none\':\'block\';'
                       . 'this.querySelector(\'span\').textContent=open?\'المزيد (' . $rest . ') ▾\':\'أقل ▴\';">'
                       . '<span>المزيد (' . $rest . ') ▾</span></button></li>' . "\n";
                    echo '<li><ul id="navmore-' . $stMoreSeq . '" style="display:none;list-style:none;padding:0;margin:0">' . "\n";
                }
                echo $k['html'];
            }
            if ($dense) { echo '</ul></li>' . "\n"; }
        }
        $stageBody = ob_get_clean();
        /* ◆ ومرحلةٌ لم ينجُ منها رابطٌ لا تُطبع — ورأسا الإدارةِ ينتقلان
             إلى أولِ مرحلةٍ تُطبع فعلًا (يُتحقَّق منه بفاحصٍ بعدَ التغيير). */
        if (!ems_nav_group_has_link($stageBody)) { continue; }

        /* ◆ والقيمةُ **تُستعمل فعلًا**: كانت تُحسب ثم يُكتب `aria-expanded="false"`
             حرفيًّا فلا أثرَ لها — متغيّرٌ مُهمَلٌ يُوهم أنَّ القاعدةَ مطبَّقة. */
        echo '<li class="nav-group' . ($openDefault ? ' open' : '') . '" data-group-key="' . $key . '"'
           . ($openDefault ? ' data-ems-open-default="1"' : '') . '>' . "\n";
        /* WCAG 2.2 AA · 4.1.2: اسمُ المرحلةِ يعيش في `span` قد يُخفيه طيُّ الشريط
           فيبقى الزرُّ بلا اسمٍ لقارئِ الشاشة — يُثبَّت على الزرِّ نفسِه. */
        echo '  <button type="button" class="nav-group-head" aria-expanded="' . ($openDefault ? 'true' : 'false') . '"'
           . ' aria-controls="navgrp-' . $key . '"'
           . ' aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"'
           . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
           . '<i class="' . $icon . '" aria-hidden="true"></i> <span class="nav-group-name">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
           // NM-05 (الوثيقة 70 §4-5): سطرُ شرحِ المرحلةِ تحتَ اسمِها — من stage_desc المبذورِ من النصِّ الحاكم
           . ((isset($sItems[0]['stage_desc']) && trim((string) $sItems[0]['stage_desc']) !== '')
               ? '<span class="nav-stage-desc">' . htmlspecialchars(trim((string) $sItems[0]['stage_desc']), ENT_QUOTES, 'UTF-8') . '</span>'
               : '')
           . '</span>' . $badge
           . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
        echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";

        // ── رأسا كل إدارة (قرار المالك 2026-08-03): «الرئيسية» تؤدي دائمًا
        // إلى لوحة الإدارة المعنية (مسلك role_board يحلّها بالدور)، وتحتها
        // «المراسلات» بشارة غير المقروء الحية (يحدّثها مستطلِع insidebar
        // القائم عبر #nav-unread-badge) — أولَ رابطين في أول مرحلة، على
        // مستوى المصيّر فيصمدان أمام كل إعادة توليدٍ من الوثيقة.
        /* ◆ وحارسُ الغلافِ الفارغ (2026-08-20): يُحقنان في أولِ مرحلةٍ **تُطبع
             فعلًا** لا في أولِ مرحلةٍ مُزمَعة — فلو حُقنا في مرحلةٍ سقطت روابطُها
             كلُّها لضاعا معها. ولذلك يُبنى جسمُ المرحلةِ قبلَهما ويُشترط نجاةُ رابط. */
        if (!$hdrPrinted) {
            $hdrPrinted = true;
            /* المفتاحُ بصيغةِ «المسار||التسمية» — نفسِ صيغةِ `printNavLinkItem`،
               وإلا لم يتعرَّف الحارسُ على المحقونِ فطُبع مرتين من مصدرين.
               ◆ UXUI-01 v3: ما يولّده سجلُّ التنقلِ لهذا الدورِ لا يُحقن هنا —
                 «لا سايدبارَ موروثٌ خلفَ المصفوفة». والحقنُ يبقى للأدوارِ التي
                 لا قياسَ current لها (الفرعيةُ ومن خارجِ نطاقِ القياس). */
            $navFromRegistry = function ($route) {
                if (!isset($GLOBALS['__uxui_cur_role'])) { return false; }
                $cur = uxuiCurrentMap($GLOBALS['conn'], $GLOBALS['__uxui_cur_role']);
                return isset($cur[$route]);
            };
            /* RPR-W02 §٤-٣: المرساتان **من السجلِّ المعياريِّ** لا من هذين السطرين.
               كان المسارُ والاسمُ مكتوبَين حرفًا هنا وفي `insidebar.php` وفي
               `printEmsTenGroupNav` — **ثلاثةُ مواضعَ لبندٍ واحد**، ويكفي أن
               يتغيَّر أحدُها ليتفرَّق ما يراه دورٌ عمّا يراه غيرُه. */
            /* ⚠ `printStageNav` لا تأخذ `$conn` وسيطًا — والاتصالُ يُقرأ من
               `$GLOBALS` كما يفعل `$navFromRegistry` أعلاه بسطرَين. و`$conn`
               المحليُّ هنا **غيرُ مُعرَّف**: تمريرُه يعطي `null` فتصمت المرساتان
               صمتًا تامًّا ولا يظهر خطأ. */
            require_once __DIR__ . '/nav_icon_map.php';
            require_once __DIR__ . '/nav_anchors.php';
            $__navConn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
            $__aHome = ems_nav_anchor($__navConn, 'HOME');
            if ($__aHome !== null && !$navFromRegistry($__aHome['route'])) {
                ems_nav_mark_printed($__aHome['route'] . '||' . $__aHome['label']);
                echo ems_nav_anchor_li($__navConn, 'HOME', $basePrefix);
            }
            $__aChat = ems_nav_anchor($__navConn, 'CHATS');
            if ($__aChat !== null && !$navFromRegistry($__aChat['route'])) {
                ems_nav_mark_printed($__aChat['route'] . '||' . $__aChat['label']);
                echo ems_nav_anchor_li($__navConn, 'CHATS', $basePrefix, 'id="sidebarChatLink"',
                    '<span id="nav-unread-badge" class="nav-count-badge" style="display:none;"></span>');
            }
        }

        echo $stageBody;
        echo '  </ul>' . "\n";
        echo '</li>' . "\n";
    }
}

/* ══ مجموعةُ المسارِ المطبوع — حارسُ التكرارِ في المولِّد ═══════════════════════
   ⇐ INJ-0414 · INJ-0489 · INJ-0540 · INJ-0448 · INJ-0222 · INJ-0562
              · INJ-0127 · INJ-0132 · INJ-0154 · INJ-0428 · INJ-0512 · INJ-0513 · INJ-0554

   **العلّةُ الواحدة**: «المراسلات» تُطبع مرتين في سايدبارِ خمسةٍ وعشرين دورًا —
   مرةً **محقونةً في المولِّد** (وهي التي تحمل شارةَ غيرِ المقروءِ الحيّة) ومرةً
   من صفٍّ في `nav_items`. وستةُ بنودٍ في السجلِّ ليست إلا عرضًا لهذه العلّة.

   ── ولماذا الحارسُ هنا لا في البذرة ─────────────────────────────────────────
   حذفُ الصفوفِ يُصلح اليوم؛ وأولُ إعادةِ توليدٍ من الوثيقةِ تُعيدها. فالمنعُ
   **في المولِّد**: مسارٌ طُبع مرةً لا يُطبع ثانيةً مهما تعدَّد مصدرُه — بذرةً
   كان أو حقنًا يدويًّا أو صفًّا يدويًّا. وهو يحرس كلَّ الأدوارِ اليومَ ومستقبلًا.

   ── وثلاثةُ ضوابطَ في التطبيع ──────────────────────────────────────────────
     ① **المرساةُ تُجرَّد**: `chats/index.php#n9g7i1` هو الملفُّ نفسُه — ومقارنةٌ
        بلا تجريدٍ تعدُّه ملفًّا آخر (INJ-0132 · 0154 · 0428 · 0512 · 0513 · 0554).
     ② **البادئةُ النسبيةُ تُجرَّد**: `../chats/index.php` = `chats/index.php`.
     ③ **وسلسلةُ الاستعلامِ تُجرَّد**: `?type=1` منظرٌ لا ملفٌّ آخر — إلا أن
        يُصرَّح بخلافِ ذلك، وحينها يُوسَم الصفُّ صراحةً.
   والمجموعةُ **لكلِّ طلبٍ**: `static` داخلَ الدالةِ يبقى ما بقيت العملية، وهو
   ما نريد — فالسايدبارُ يُصيَّر مرةً في الطلبِ الواحد. */
if (!function_exists('ems_nav_norm_route')) {
    /**
     * @param bool $dropAnchor هل تُجرَّد المِرساة؟
     *   `false` (الافتراض) = **هويةُ الرابط**: مِرساتانِ مختلفتانِ مدخلانِ
     *      مقصودان لقسمين في الشاشةِ نفسِها (INJ-0459) — فلا يُبتلع أحدُهما.
     *   `true` = **هويةُ الملفّ**: للقياسِ الذي يسأل «كم مرةً ظهر هذا الملفُّ؟».
     *
     * ◆ وقع الفخُّ فعلًا: أوّلُ صياغةٍ جرّدت المِرساةَ في **الحارس** فابتلعت
     *   أربعين رابطًا مشروعًا (١٢٧٦ ⇒ ١٢٣٦) وصارت ٣٩ صفًّا «شاشاتٍ ميتة».
     *   فالحارسُ يقارن بالهويةِ الكاملة، والقياسُ وحدَه يجرّد.
     */
    function ems_nav_norm_route($route, $dropAnchor = false)
    {
        $r = (string) $route;
        $anchor = '';
        if (strpos($r, '#') !== false) {
            $parts = explode('#', $r, 2);
            $r = $parts[0];
            $anchor = trim($parts[1]);
        }
        /* ◆ **والمنظرُ المُعلَنُ جزءٌ من هويةِ الرابطِ كالمِرساة**: `?view=` مُعلَنٌ
             في `includes/nav_views.php` يفتح محتوًى مختلفًا فعلًا (قسمًا مركَّزًا
             أو ترشيحًا بعمود)، فطيُّه في اسمِ الملفِّ يجعل وجهتين تبدوان واحدة.
             وغيرُ المُعلَنِ **لا يُميّز** — فلا يُهرَّب به صفٌّ مكرَّرٌ من الحارس. */
        $view = '';
        if ($anchor === '' && strpos($r, 'view=') !== false) {
            require_once __DIR__ . '/nav_views.php';
            if (ems_nav_route_has_view($r) && preg_match('~[?&]view=([^&#]+)~', $r, $mv)) {
                $view = strtolower(urldecode($mv[1]));
            }
        }
        $r = explode('?', $r, 2)[0];                 /* سلسلةُ الاستعلامِ منظرٌ لا ملف */
        $r = preg_replace('~^(\.\./)+~', '', $r);    /* البادئةُ النسبية */
        $r = strtolower(trim(ltrim((string) $r, '/')));
        if ($dropAnchor) { return $r; }
        if ($anchor !== '') { return $r . '#' . strtolower($anchor); }
        if ($view !== '') { return $r . '?view=' . $view; }
        return $r;
    }
}
if (!function_exists('ems_nav_mark_printed')) {
    /** @return bool true إن كان أوّلَ ظهورٍ لهذا المسار (فيُطبع)، false إن تكرَّر */
    function ems_nav_mark_printed($route, $reset = false)
    {
        static $seen = array();
        if ($reset) { $seen = array(); return true; }
        /* ── متى يكون رابطانِ تكرارًا؟ شرطانِ لا شرطٌ واحد ────────────────────────
             ⓐ **الملفُّ نفسُه بالتسميةِ نفسِها** — لا شيءَ يميّزهما للمستخدم.
             ⓑ **الملفُّ نفسُه وكلاهما بلا مِرساة** — يهبطانِ في المكانِ نفسِه
                ولو اختلفت التسمية («الرئيسية» و«لوحة الإدارة» لـ`role_board`).
             وما عدا ذلك — ملفٌّ بمِرساتين مختلفتين — **مدخلانِ مقصودانِ** لقسمين،
             ويُطبعانِ (وهو ما تحرسه مِراسي الوصولِ في INJ-0459). */
        $label = '';
        if (strpos((string) $route, '||') !== false) {
            $parts = explode('||', (string) $route, 2);
            $route = $parts[0];
            $label = trim($parts[1]);
        }
        $file = ems_nav_norm_route($route, true);          /* بلا مِرساة */
        if ($file === '') { return true; }
        /* ── وما الذي يُميّز رابطين على ملفٍّ واحد؟ ───────────────────────────
           مِرساةٌ (`#`) — أو **مِنظرٌ مُعلَنٌ** في `includes/nav_views.php`.
           والقيدُ «مُعلَن» جوهريّ: `?view=` غيرُ مُعلَنٍ لا يُغيّر ما يُعرض
           (قِيس: صفرٌ من ١٥ ملفًّا يقرأ `$_GET['view']` بنفسِه)، فلو عُدَّ
           مميِّزًا لصار بابًا يُهرَّب منه كلُّ صفٍّ مكرَّرٍ من الحارس. */
        $hasAnchor = (strpos((string) $route, '#') !== false);
        if (!$hasAnchor && strpos((string) $route, 'view=') !== false) {
            require_once __DIR__ . '/nav_views.php';
            $hasAnchor = ems_nav_route_has_view($route);
        }

        $kLabel = $file . '||' . $label;                   /* ⓐ */
        $kBare  = $hasAnchor ? null : ($file . '||#bare'); /* ⓑ */

        if (isset($seen[$kLabel])) { return false; }
        if ($kBare !== null && isset($seen[$kBare])) { return false; }
        $seen[$kLabel] = true;
        if ($kBare !== null) { $seen[$kBare] = true; }
        return true;
    }
}

/* ── مراسي الوصول · INJ-0459 ─────────────────────────────────────────────────
   بعضُ روابطِ القوائمِ تحمل مِرساةً في آخرها (`Finance/approvals_inbox.php#n9g32i3`)
   تُميّز عنصرَ قائمةٍ عن آخرَ يقصد **الشاشةَ نفسَها** من مرحلةٍ أخرى. وكانت
   المِراسُ الثمانُ والسبعون كلُّها **بلا وجودٍ في وجهاتها**: المتصفحُ يبتلع
   الجزءَ صامتًا فلا ينتقل ولا يُنبِّه. تُبَثُّ هنا مرةً واحدةً لكلِّ عنصرٍ
   يقصد الشاشةَ الحالية — إصلاحٌ في العُدَّةِ لا في ثمانٍ وسبعين شاشة.
   (السجلُّ ذكر أربعةَ روابطَ في دور ٣؛ والقياسُ الحيُّ أعطى ٧٨ عبرَ الأدوار.) */
function emsNavLandingAnchors(array $items) {
    $cur = basename((string) (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''));
    $dir = basename(dirname((string) (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')));
    $out = array();
    foreach ($items as $it) {
        $route = isset($it['route']) ? (string) $it['route'] : '';
        if (strpos($route, '#') === false) { continue; }
        $parts = explode('#', $route, 2);
        $frag  = trim($parts[1]);
        /* مِرساةٌ غيرُ صالحةٍ كمعرِّفٍ لا تُبَثُّ — فمعرِّفٌ مشوَّهٌ أسوأُ من غيابه */
        if ($frag === '' || !preg_match('~^[A-Za-z][A-Za-z0-9_\-]*$~', $frag)) { continue; }
        $path = ltrim(preg_replace('~^(\.\./)+~', '', $parts[0]), '/');
        if (basename($path) !== $cur) { continue; }
        $pdir = basename(dirname($path));
        if ($pdir !== '.' && $pdir !== '' && $pdir !== $dir) { continue; }
        $out[$frag] = true;
    }
    if (!$out) { return 0; }
    echo '<div class="ems-nav-landing" aria-hidden="true" style="height:0;overflow:hidden">';
    foreach (array_keys($out) as $frag) {
        echo '<span id="' . htmlspecialchars($frag, ENT_QUOTES, 'UTF-8') . '" style="scroll-margin-top:96px"></span>';
    }
    echo '</div>';
    return count($out);
}

/* ═══ UXUI-01 (ف١٥ بند ٦): السايدبارُ يُولَّد من سجلِّ التنقلِ المعياريّ ═══
   nav_canonical صورةُ «مصفوفة التنقل المعيارية» المعتمَدة (359):
   · APPROVED (272) يُولَّد بالاسمِ والمجموعةِ والمستوى والترتيبِ المعياريةِ —
     في كلِّ الأدوارِ سواءً (بندا ٢ و٣: same_route ⇒ same_label/same_group).
   · PENDING_OWNER/PENDING_DEDUP لا يُولَّد ولا يُمَسُّ موضعُه الحاليُّ (ف١٥-٢)
     حتى توقيعِ «جلسة إغلاق المعلَّق» — فيبقى في مجموعتِه المرحليةِ القائمة.
   · الدورُ يبقى طبقةَ ظهورٍ فقط (بند ٥): التبعيةُ في nav_items كما هي،
     وما يظهر للدورِ هو صفوفُه هو — لكنَّ اسمَه وموضعَه من السجلِّ الواحد.
   · الرابطُ ذو المرساةِ أو المنظرِ (#n · ?view=) مدخلٌ مقصودٌ يحفظ تسميتَه
     ويرث مجموعةَ ملفِّه الأم (بندا ٣ و٧) — فلا يضيع رابطٌ واحد (صفرُ فقد).
   · مفتاحُ إيقافٍ تشغيليّ: EMS_UXUI_NAV=off في البيئةِ يعيد الوضعَ القديمَ حرفًا. */
function uxuiNavBaseRoute($route) {
    $r = preg_replace('~^(\.\./)+~', '', trim((string) $route));
    $r = preg_replace('/[?#].*$/u', '', $r);
    return strtolower(trim($r, '/'));
}

function uxuiCanonicalMap($conn) {
    /* v3 (تفويض 2026-08-18): السجلُّ كلُّه بحالاتِه — «المولّدُ يقرأ من المصفوفةِ
       وحدَها ويتصرف بالحالة: APPROVED يأخذ المعياريَّ · PENDING_OWNER يأخذ الحاليَّ
       من الصفِّ نفسِه · PENDING_OWNER_MERGE يبقى بمسارِه حتى تنفيذِ الدمج —
       لا سايدبارَ موروثٌ خلفَ المصفوفةِ أبدًا». */
    static $map = null;
    if ($map !== null) { return $map; }
    $map = array();
    if (function_exists('ems_env') && strtolower((string) ems_env('EMS_UXUI_NAV')) === 'off') { return $map; }
    $res = @mysqli_query($conn, "SELECT route, canonical_ar, level_no, group_name, sort_no, status, current_label
                                   FROM nav_canonical");
    if (!$res) { return $map; } /* الجدولُ لم يُبذر بعدُ = الوضعُ القديمُ كما هو (fail-open للعرض) */
    while ($row = mysqli_fetch_assoc($res)) { $map[strtolower(trim($row['route']))] = $row; }
    return $map;
}

/**
 * القسمُ المُعلَنُ لكلِّ دور — من `gov_target_nav` وحدَه (INJ-FRD-01 · XC-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلةُ المقيسة**: `gov_target_nav` يصف **ستَّ مجموعاتٍ لكلِّ دور**
 *   (مفتاحُه `role_id`+`group_no`)، بينما رأسُ الطيِّ المُصيَّرَ يأتي من
 *   `nav_route_group` **ومفتاحُه المسارُ وحدَه** (472 صفًّا · لا `role_id`)
 *   ضمنَ تصنيفٍ سقفُه اثنتا عشرةَ مجموعةً مشتركةً بين الأدوارِ التسعةَ عشر.
 *   فمجموعتانِ مستهدفتانِ تنهاران على رأسٍ واحد.
 *
 * ⚠ **وما دونَه تاريخُ الطبقةِ لا وصفُها اليوم** (RPR-OPS-02): بدأت قسمًا
 *   فرعيًّا تحتَ رأسِ التصنيف، **ثمَّ صارت المجموعةُ المُعلَنةُ رأسَ الطيِّ نفسَه**
 *   لمن أعلن — انظر بناءَ `$declHead` في `printEmsTenGroupNav`.
 *
 * ◆ **والحلُّ الأقلُّ تدخّلًا** (الطبقةُ الأولى، وما زال يسري لمن لا إعلانَ له):
 *   الطبقةُ الثانيةُ للسايدبار — **القسمُ الفرعيُّ**
 *   (`nav-subhead`) — **لكلِّ دورٍ سلفًا**، والمُصيِّرُ يدعمها منذ 2026-08-17
 *   («عشرُ رؤوسٍ للتوجُّهِ وأقسامٌ للمسح»). فتُحمَل المجموعاتُ المستهدفةُ عليها
 *   بلا مساسٍ بسقفِ التصنيفِ ولا بالأدوارِ الأخرى.
 *
 * ◆ **ولماذا لم يُقلَبِ الترتيبُ عامًّا**: قِيس أنَّ `cur_group` يخالف
 *   `group_name` في **791 زوجًا من 870 في تسعةَ عشرَ دورًا** — فتفضيلُ
 *   الحاليِّ عامًّا يُعيد رسمَ بنيةِ المعلوماتِ للمنصّةِ كلِّها. **فالإعلانُ
 *   وحدَه يسري**: لا يُطبَّق إلا حيث نشرت الإدارةُ جدولَها المستهدَف.
 *
 * ◆ قراءةٌ خالصةٌ ومحفوظةٌ ساكنًا — والجدولُ الغائبُ يعني «لا إعلان» فيبقى
 *   السلوكُ السابقُ حرفًا (fail-open للعرض كنظائرِه في هذا الملف).
 */
/* ◆ **والإعلانُ يحمل الترتيبَ كما يحمل الاسمَ** (RPR-OPS-01): مفتاحُ الجدولِ
     `role_id`+`group_no`+`item_no` — **فالرتبةُ منصوصةٌ فيه** ومع ذلك كان
     المُصيِّرُ يقرأ `group_ar` وحدَه ويرتّب بـ`nav_canonical.sort_no`، **وهو
     مفتاحُه المسارُ لا الدور**. فورقةُ الدليلِ تقول «ترتيبُها في هذا الشيت هو
     ترتيبُها في الـSidebar» ولا سبيلَ إلى إنفاذِه: أيُّ ضبطٍ عالميٍّ للرتبةِ
     يُعيد ترتيبَ التسعةَ عشرَ دورًا لأجلِ دورٍ واحد.
   ⇒ **الرتبةُ المُعلَنةُ تسري على المُعلَنِ وحدَه**، ومن لا صفَّ له في الجدولِ
     يبقى على `sort_no` حرفًا. ويعود الصفُّ الآن مصفوفةً `g` (القسم) و`o`
     (الرتبة) — والمستهلكُ الوحيدُ حلقةُ التصييرِ أدناه. */
/* ═══ NAVR (أمرُ «إصلاح الملاحة من الجذور» 2026-08-31) — طبقةُ المواضعِ الحاكمة ═══
 * ◆ المصدرُ الحاكمُ لمجموعاتِ دورةِ العملِ صار `nav_placements` (المستورَدُ آليًّا
 *   من ورقةِ الدليلِ المعماريِّ لكلِّ إدارةٍ عبر `tools/navr_import_guide.php`) —
 *   لا `gov_target_nav` الذي قِيس أنَّ 94٪ من صفوفِه أُلِّفت من التصييرِ القائمِ
 *   (`RENDER-ALIGN`) فصار Target مولَّدًا من Current يقيس نفسَه.
 * ◆ **ولا سقوطَ صامتًا** (BUSINESS_WORKSPACE_GLOBAL_FALLBACK=0): دورٌ مربوطٌ
 *   بمساحةِ أعمالٍ ذاتِ مواضعَ لا يجوز أن يُصيَّر بالتصنيفِ العامِّ — وإن وقع
 *   يُقيَّد Finding في `gov_nav_findings` ويُعلَن مرئيًّا في بيئةِ الاختبار
 *   (`EMS_NAV_STRICT=on`). ودورٌ بلا مساحةٍ/مواضعَ يسلك سلوكَه السابقَ حرفًا
 *   — **معلَنًا لا صامتًا** (المساحاتُ غيرُ المغطّاةِ مقيسةٌ في navr_metrics). */
function navrRecordFinding($conn, $kind, $roleId, $wsId, $detail) {
    $k = $conn->real_escape_string($kind);
    $w = $wsId !== null ? "'" . $conn->real_escape_string($wsId) . "'" : 'NULL';
    $r = $roleId !== null ? (int) $roleId : 'NULL';
    $d = $conn->real_escape_string(mb_substr($detail, 0, 380));
    @mysqli_query($conn, "INSERT INTO gov_nav_findings (kind, role_id, workspace_id, detail)
        VALUES ('{$k}', {$r}, {$w}, '{$d}')
        ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW(), detail = VALUES(detail)");
}

/** أقسامُ طبقةِ المواضع لدورٍ: [map(base⇒{g,n,o,p}), workspace_id|null] */
function navrPlacementSections($conn, $roleId) {
    static $byRole = array();
    $rid = (int) $roleId;
    if (isset($byRole[$rid])) { return $byRole[$rid]; }
    $byRole[$rid] = array(array(), null);
    $q = @mysqli_query($conn, "SELECT wr.workspace_id
                                 FROM nav_ws_roles wr
                                 JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id AND w.active = 1
                                WHERE wr.role_id = {$rid} AND wr.binding = 'PRIMARY'
                                  AND w.kind IN ('DEPARTMENT','EXECUTIVE') LIMIT 1");
    if (!$q || !$q->num_rows) { return $byRole[$rid]; }
    $ws = (string) $q->fetch_row()[0];
    $byRole[$rid][1] = $ws;
    $res = @mysqli_query($conn, "SELECT p.route, g.label_ar, g.sort_no AS gno, p.sort_no AS ino
                                   FROM nav_placements p
                                   JOIN nav_lifecycle_groups g ON g.id = p.group_id AND g.active = 1
                                  WHERE p.workspace_id = '" . $conn->real_escape_string($ws) . "'
                                    AND p.active = 1 AND p.placement_type = 'MENU_ITEM'
                                    AND p.route IS NOT NULL");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $base = uxuiNavBaseRoute($row['route']);
            if ($base === '') { continue; }
            $byRole[$rid][0][$base] = array(
                'g' => (string) $row['label_ar'],
                'n' => (int) $row['gno'],
                'o' => ((int) $row['gno'] * 1000) + (int) $row['ino'],
                'p' => 1, /* مجموعةُ ورقةِ الدليلِ بابٌ دائمًا */
            );
        }
    }
    return $byRole[$rid];
}

function uxuiDeclaredSections($conn, $roleId) {
    static $byRole = array();
    $rid = (int) $roleId;
    if (isset($byRole[$rid])) { return $byRole[$rid]; }
    $byRole[$rid] = array();
    /* ── NAVR: طبقةُ المواضعِ تغلب متى وُجدت — والغيابُ يُقيَّد لا يُبتلع ── */
    if (!function_exists('ems_env') || strtolower((string) ems_env('EMS_NAV_WORKSPACE', 'on')) !== 'off') {
        list($pl, $ws) = navrPlacementSections($conn, $rid);
        if (!empty($pl)) { $byRole[$rid] = $pl; return $pl; }
        if ($ws !== null) {
            navrRecordFinding($conn, 'EMPTY_WORKSPACE_PLACEMENTS', $rid, $ws,
                'مساحة مربوطة بلا مواضع قوائم مبنية صالحة، سقوط للمسار السابق يقاس ولا يبتلع');
        }
    }
    $res = @mysqli_query($conn, "SELECT route, group_ar, group_no, item_no, doc_code
                                   FROM gov_target_nav WHERE role_id = {$rid}");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            /* `GAP:` علامةُ منصوصٍ بلا شاشةٍ حيّة — يُقاس في الجدولِ ولا يُصيَّر */
            if (strncmp((string) $row['route'], 'GAP:', 4) === 0) { continue; }
            $base = uxuiNavBaseRoute($row['route']);
            if ($base === '') { continue; }
            $byRole[$rid][$base] = array(
                'g' => (string) $row['group_ar'],
                'n' => (int) $row['group_no'],
                'o' => ((int) $row['group_no'] * 1000) + (int) $row['item_no'],
                /* SIDEBAR_DIRECTION_FIX: إعلانُ الورقةِ وحدَه يُرقّى بابًا —
                   وإعلانُ محاذاةِ الملفِّ (`RENDER-ALIGN%`) عنوانٌ فرعيٌّ
                   داخل بابِ تصنيفِه، فلا تنفجر الأبوابُ بعددِ مجموعاتِ الملفّ */
                'p' => (strncmp((string) $row['doc_code'], 'RENDER-ALIGN', 12) !== 0) ? 1 : 0,
            );
        }
    }
    return $byRole[$rid];
}

/** موضعُ المعلَّقِ الحاليُّ لكلِّ دور — من nav_canonical_current (المصفوفةُ وحدَها) */
function uxuiCurrentMap($conn, $roleId) {
    static $byRole = array();
    $rid = (int) $roleId;
    if (isset($byRole[$rid])) { return $byRole[$rid]; }
    $byRole[$rid] = array();
    $res = @mysqli_query($conn, "SELECT route, cur_label, cur_group, cur_order FROM nav_canonical_current WHERE role_id = {$rid}");
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { $byRole[$rid][strtolower(trim($row['route']))] = $row; } }
    return $byRole[$rid];
}

/** كتلةُ المعلَّقِ بموضعِه الحاليِّ من السجل — مجموعاتُه الحيّةُ بترتيبِ ظهورِها.
 *  $mode: 'flat' يطبع بنودَ «— خارج التبويب» وحدَها (الرئيسيةُ أولَ ما يُرى) ·
 *         'groups' يطبع المجموعاتِ وحدَها — فيحيط المسطّحُ القائمةَ من أولِها
 *         والمجموعاتُ المعلَّقةُ تلحق الكتلةَ المعيارية */
function printUxuiCurrentNav($items, $curMap, $basePrefix, $badges, $mode = 'groups') {
    if (empty($items)) { return; }
    $rows = array(); $flat = array();
    foreach ($items as $idx => $it) {
        $raw = (string) $it['route'];
        $base = uxuiNavBaseRoute($raw);
        $isVariant = (strpbrk($raw, '#?') !== false);
        $cur = isset($curMap[$base]) ? $curMap[$base] : null;
        $grp = $cur ? $cur['cur_group'] : '— خارج التبويب';
        $ord = $cur ? (int) $cur['cur_order'] : 999;
        $lbl = $isVariant ? $it['label_ar'] : ($cur ? $cur['cur_label'] : $it['label_ar']);
        $row = array('code' => $raw, 'name' => $lbl, 'icon' => $it['icon'],
                     'group' => $grp, 'sort' => $ord, 'variant' => $isVariant ? 1 : 0, 'idx' => $idx);
        if ($grp === '— خارج التبويب' || $grp === '') { $flat[] = $row; } else { $rows[] = $row; }
    }
    if ($mode === 'flat') {
        usort($flat, function ($a, $b) { return ($a['sort'] !== $b['sort']) ? $a['sort'] - $b['sort'] : $a['idx'] - $b['idx']; });
        foreach ($flat as $r) {
            printNavLinkItem(array('code' => $r['code'], 'name' => $r['name'], 'icon' => $r['icon']), $basePrefix, $badges);
        }
        return;
    }
    if (empty($rows)) { return; }
    $groups = array();
    foreach ($rows as $r) { $groups[$r['group']]['items'][] = $r; }
    foreach ($groups as $gname => &$G) {
        $ms = PHP_INT_MAX;
        foreach ($G['items'] as $r) { if ($r['sort'] < $ms) { $ms = $r['sort']; } }
        $G['minsort'] = $ms; $G['name'] = $gname;
        usort($G['items'], function ($a, $b) {
            if ($a['sort'] !== $b['sort']) { return $a['sort'] - $b['sort']; }
            if ($a['variant'] !== $b['variant']) { return $a['variant'] - $b['variant']; }
            return $a['idx'] - $b['idx'];
        });
    }
    unset($G);
    $list = array_values($groups);
    usort($list, function ($a, $b) { return ($a['minsort'] !== $b['minsort']) ? $a['minsort'] - $b['minsort'] : strcmp($a['name'], $b['name']); });
    $seq = 0;
    foreach ($list as $G) {
        $seq++;
        $key = 'uxp-' . $seq;
        $total = 0;
        foreach ($G['items'] as $r) { $total += isset($badges[$r['code']]) ? intval($badges[$r['code']]) : 0; }
        $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';
        /* ① الروابطُ أولًا · ② ثم الغلافُ إن نجا رابط (حارسُ الغلافِ الفارغ) */
        ob_start();
        foreach ($G['items'] as $r) {
            printNavLinkItem(array('code' => $r['code'], 'name' => $r['name'], 'icon' => $r['icon']), $basePrefix, $badges);
        }
        $body = ob_get_clean();
        if (!ems_nav_group_has_link($body)) { continue; }
        echo '<li class="nav-group open" data-group-key="' . $key . '">' . "\n";
        /* WCAG 2.2 AA · 4.1.2 — الاسمُ على الزرِّ لا في `span` قد يُخفيه الطيّ */
        $gnm = htmlspecialchars($G['name'], ENT_QUOTES, 'UTF-8');
        echo '  <button type="button" class="nav-group-head" aria-expanded="true" aria-controls="navgrp-' . $key . '"'
           . ' aria-label="' . $gnm . '" title="' . $gnm . '">'
           . '<i class="fa fa-folder" aria-hidden="true"></i> <span class="nav-group-name">'
           . $gnm . '</span>' . $badge
           . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
        echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";
        echo $body;
        echo '  </ul>' . "\n" . '</li>' . "\n";
    }
}

/** طباعةُ الكتلةِ المعيارية: مجموعاتٌ بترتيبِ (المستوى ثم الدورةِ) — بهيكلِ nav-group نفسِه */
function printUxuiCanonicalNav($items, $map, $basePrefix, $badges) {
    if (empty($items)) { return; }
    $rows = array();
    foreach ($items as $idx => $it) {
        $raw = (string) $it['route'];
        $base = uxuiNavBaseRoute($raw);
        if (!isset($map[$base])) { continue; }
        $c = $map[$base];
        $isVariant = (strpbrk($raw, '#?') !== false); /* مدخلٌ ثانٍ مقصودٌ — تسميتُه له */
        $rows[] = array(
            'code'    => $raw,
            'name'    => $isVariant ? $it['label_ar'] : $c['canonical_ar'],
            'icon'    => $it['icon'],
            'level'   => (int) $c['level_no'],
            'group'   => $c['group_name'],
            'sort'    => (int) $c['sort_no'],
            'variant' => $isVariant ? 1 : 0,
            'idx'     => $idx,
        );
    }
    if (empty($rows)) { return; }
    $groups = array();
    foreach ($rows as $r) { $groups[$r['group']]['items'][] = $r; }
    foreach ($groups as $gname => &$G) {
        $lv = 99; $ms = PHP_INT_MAX;
        foreach ($G['items'] as $r) { if ($r['level'] < $lv) { $lv = $r['level']; } if ($r['sort'] < $ms) { $ms = $r['sort']; } }
        $G['level'] = $lv; $G['minsort'] = $ms; $G['name'] = $gname;
        usort($G['items'], function ($a, $b) {
            if ($a['sort'] !== $b['sort']) { return $a['sort'] - $b['sort']; }
            if ($a['variant'] !== $b['variant']) { return $a['variant'] - $b['variant']; } /* الأصلُ قبل منظرِه */
            return $a['idx'] - $b['idx'];
        });
    }
    unset($G);
    $list = array_values($groups);
    usort($list, function ($a, $b) {
        if ($a['level'] !== $b['level']) { return $a['level'] - $b['level']; }
        if ($a['minsort'] !== $b['minsort']) { return $a['minsort'] - $b['minsort']; }
        return strcmp($a['name'], $b['name']);
    });
    $levelIcons = array(1 => 'fa fa-tachometer-alt', 2 => 'fa fa-briefcase', 3 => 'fa fa-database',
                        4 => 'fa fa-chart-pie', 5 => 'fa fa-book', 6 => 'fa fa-cog');
    $seq = 0;
    foreach ($list as $G) {
        $seq++;
        $key = 'uxc-' . $seq;
        $open = ($G['level'] <= 3); /* ف٧-٢: مطويٌّ افتراضيًّا للمستوياتِ ٤ و٥ و٦ */
        $icon = isset($levelIcons[$G['level']]) ? $levelIcons[$G['level']] : 'fa fa-folder';
        $total = 0;
        foreach ($G['items'] as $r) { $total += isset($badges[$r['code']]) ? intval($badges[$r['code']]) : 0; }
        $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';
        /* ① الروابطُ أولًا · ② ثم الغلافُ إن نجا رابط (حارسُ الغلافِ الفارغ) */
        ob_start();
        foreach ($G['items'] as $r) {
            printNavLinkItem(array('code' => $r['code'], 'name' => $r['name'], 'icon' => $r['icon']), $basePrefix, $badges);
        }
        $body = ob_get_clean();
        if (!ems_nav_group_has_link($body)) { continue; }
        echo '<li class="nav-group' . ($open ? ' open' : '') . '" data-group-key="' . $key . '">' . "\n";
        /* الوصولُ الرقميُّ (WCAG 2.2 AA · بوابةُ الترقيةِ البند ٣): رأسُ المجموعةِ
           **زرٌّ**، واسمُه يعيش في `span` قد يُخفيه طيُّ السايدبار. و`innerText`
           لعنصرٍ مخفيٍّ فراغٌ — فيبقى الزرُّ ظاهرًا بلا اسمٍ لقارئِ الشاشة.
           قِيس حيًّا: 37 زرًّا بلا اسمٍ في شاشةٍ واحدة. فالاسمُ يُثبَّت على الزرِّ
           نفسِه (`aria-label` + `title`) فلا يسقط بطيِّ الشريط — وهو أيضًا حكمُ
           ف١٢-٢: «الأيقونيُّ بتلميحٍ نصيٍّ إلزاميّ». */
        $gnameAttr = htmlspecialchars($G['name'], ENT_QUOTES, 'UTF-8');
        echo '  <button type="button" class="nav-group-head" aria-expanded="' . ($open ? 'true' : 'false') . '"'
           . ' aria-controls="navgrp-' . $key . '" aria-label="' . $gnameAttr . '" title="' . $gnameAttr . '">'
           . '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> <span class="nav-group-name">'
           . $gnameAttr . '</span>' . $badge
           . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
        echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";
        echo $body;
        echo '  </ul>' . "\n" . '</li>' . "\n";
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 * العشرُ مجموعاتٍ (نصُّ المالك 2026-08-17) — رأسٌ واحدٌ لكلِّ مجموعةٍ لا رأسان
 * ══════════════════════════════════════════════════════════════════════════
 * ◆ **العيبُ الذي تُغلقه هذه الطبقة**: الرؤوسُ كانت تُطبع من **مصدرين
 *   منفصلَين** (`printUxuiCanonicalNav` للمعتمَدِ و`printUxuiCurrentNav`
 *   للمعلَّق) وكلٌّ يفتح غلافَه — فبلغ وسطيُّ الرؤوسِ ٢١ رأسًا للإدارة وأعلاه
 *   ٤٥. وهنا **مسارٌ واحدٌ للطباعة**: كلُّ البنودِ تُنسب ثم تُطبع مرةً واحدة.
 *
 * ◆ **طبقتان**: عشرُ رؤوسٍ للتوجُّه، وتحتَها أقسامٌ بعناوينِ المصفوفةِ الدقيقة
 *   للمسح — فلا يُفقد المعنى الذي بُني عبرَ الجولات، بل يهبط درجةً.
 *
 * ◆ **والاسمُ لا يتغيّر بتغيُّرِ الموضع**: المعتمَدُ يأخذ `canonical_ar` والمعلَّقُ
 *   `cur_label` والمرساةُ/المنظرُ اسمَه هو — كما كان حرفًا قبلَ هذه الطبقة.
 * ══════════════════════════════════════════════════════════════════════════ */

/** تعريفُ العشرةِ من القاعدةِ إن بُذرت، وإلا من الملفِّ — والفراغُ يعني: لا تُفعَّل. */
function emsNavTaxonomy($conn) {
    static $tax = null;
    if ($tax !== null) { return $tax; }
    $tax = array();
    if (function_exists('ems_env') && strtolower((string) ems_env('EMS_NAV_TEN')) === 'off') { return $tax; }
    $res = @mysqli_query($conn, "SELECT code, name_ar, icon, sort_no, open_default
                                   FROM nav_group_taxonomy ORDER BY sort_no");
    if (!$res) { return $tax; } /* لم تُبذر بعد = السلوكُ السابقُ حرفًا (fail-open) */
    while ($row = mysqli_fetch_assoc($res)) { $tax[$row['code']] = $row; }
    return $tax;
}

/** نسبةُ المساراتِ المخزَّنة — وما لا صفَّ له يُحكم عليه وقتَ التشغيلِ بالقاعدةِ نفسِها. */
function emsNavRouteGroupMap($conn) {
    static $map = null;
    if ($map !== null) { return $map; }
    $map = array();
    $res = @mysqli_query($conn, "SELECT route, group_code FROM nav_route_group");
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { $map[strtolower(trim($row['route']))] = $row['group_code']; } }
    return $map;
}

/**
 * تصييرُ القائمةِ كلِّها في عشرِ مجموعاتٍ — بلوكٌ واحدٌ لا بلوكان.
 *
 * @param array  $items     بنودُ الدورِ الظاهرةُ (بعدَ الصلاحيةِ والمساحة)
 * @param array  $uxMap     المصفوفةُ المعيارية (route => صفّ)
 * @param array  $uxCurMap  موضعُ المعلَّقِ الحاليُّ لهذا الدور
 * @param string $afterHome حقنةُ المراسلاتِ الخام — تُسقَط إن ولَّدها السجل
 * @return bool هل طُبع شيء
 */
function printEmsTenGroupNav($conn, $items, $uxMap, $uxCurMap, $basePrefix, $badges, $afterHome = '') {
    $tax = emsNavTaxonomy($conn);
    if (empty($tax)) { return false; }
    $rgMap = emsNavRouteGroupMap($conn);
    if (!function_exists('ems_nav_group_for_route')) {
        require_once __DIR__ . '/nav_groups.php';
    }
    /* الأقسامُ المُعلَنةُ لهذا الدور — من `gov_target_nav` وحدَه (XC-01).
       والدورُ يُقرأ من مقبضِ الجلسةِ نفسِه الذي تقرأ منه بقيةُ هذه الدالّة. */
    $declSec = isset($GLOBALS['__uxui_cur_role'])
        ? uxuiDeclaredSections($conn, (int) $GLOBALS['__uxui_cur_role'])
        : array();

    /* ══ الأبواب: المجموعةُ المُعلَنةُ رأسُ طيٍّ لا عنوانًا تحتَ رأسٍ غيرِها ════
       ◆ **المقيسُ قبلَ الحكم**: `nav_route_group` مفتاحُه المسارُ وحدَه (472 صفًّا
         بلا `role_id`) ضمنَ تصنيفٍ سقفُه اثنتا عشرةَ مجموعةً مشتركةً بين
         الأدوارِ التسعةَ عشر — **فمجموعتانِ مستهدفتانِ تنهاران على رأسٍ واحد**.
         ولذلك حُملت مجموعاتُ الدليلِ أوّلًا على `nav-subhead`: تظهر بأسمائها
         **لكن تحتَ رأسٍ ليس اسمَها** («التخطيط والتوزيع» داخلَ «التشغيل
         اليومي»)، ودورةُ الإدارةِ تُقرأ درجةً أخفضَ من دورةِ منصّةٍ عامّة.
       ◆ **والقرارُ الآن**: مَن أعلن جدولَه المستهدَفَ في `gov_target_nav`
         **صارت مجموعاتُه أبوابَه** — رأسَ طيٍّ باسمِها وأيقونتِها وترتيبِها
         (`group_no`)، والعنوانُ الفرعيُّ يسقط عنها فلا يُقرأ الاسمُ مرّتين.
       ⛔ **ولا يسري إلّا على المُعلَن**: من لا صفَّ له في الجدولِ يبقى على
         التصنيفِ الاثنَي عشرَ حرفًا — خمسةَ عشرَ دورًا لا تُمَسّ.
       ◆ **و«مساحتي» تبقى أوّلًا**: فيها المرساتان («الرئيسية» و«المراسلات»)
         وهما بنصِّ الدستورِ §6 أولُ ما يُرى — فالأبوابُ المُعلَنةُ تليها. */
    $declHead = array();                        /* group_no => رمزُ البابِ المُعلَن */
    if (!empty($declSec)) {
        require_once __DIR__ . '/nav_icon_map.php';
        $gn = array();
        /* SIDEBAR_DIRECTION_FIX §٢-②: الترقيةُ بابًا لإعلانِ الورقةِ وحدَه —
           فمجموعاتُ الملفِّ المُحاذاةُ عناوينُ فرعيّةٌ لا أبواب (٣٧ ⇒ ≤٢٠) */
        foreach ($declSec as $d) {
            if (empty($d['p'])) { continue; }
            $gn[(int) $d['n']] = (string) $d['g'];
        }
        ksort($gn);
        $head = array(); $tail = $tax;
        if (isset($tax[$anchorHomeCode = (isset($tax['MINE']) ? 'MINE' : key($tax))])) {
            $head[$anchorHomeCode] = $tax[$anchorHomeCode];
            unset($tail[$anchorHomeCode]);
        }
        foreach ($gn as $no => $name) {
            $code = 'DECL' . $no;
            $declHead[$no] = $code;
            $head[$code] = array('code' => $code, 'name_ar' => $name,
                'icon' => ems_nav_stage_icon($no, $name), 'sort_no' => $no, 'open_default' => 0);
        }
        $tax = $head + $tail;
    }

    /* مرساتا كلِّ سايدبار — اسمٌ واحدٌ وموضعٌ واحدٌ في كلِّ إدارة */
    $ANCHOR_LABEL = array('main/role_board.php' => 'الرئيسية', 'chats/index.php' => 'المراسلات');

    /* ══ «الرئيسية» أولُ رابطٍ في كلِّ شاشة (قرارُ المالك 2026-08-17) ═════════
       ◆ **المقيسُ قبلَ الحكم**: «الرئيسية» كانت تهبط **خامسةً** في سايدبارِ
         الأسطولِ و**ثانيةً** في المالية — لأنها تأخذ ترتيبَها من `sort_no`
         في المصفوفة، وصفُّها هناك في طبقةِ التقارير (المستوى الرابع).
       ◆ فتُنتزع من ترتيبِ المصفوفةِ نزعًا: **قسمُها الفراغُ** (فتقع في صدرِ
         المجموعةِ المكشوفِ قبلَ كلِّ عنوانٍ فرعيّ) و**ترتيبُها أصغرُ من كلِّ
         ترتيب**. والمراسلاتُ تليها مباشرةً — بالعُرفِ نفسِه الذي كان.
       ◆ ولا يُغيَّر اسمٌ ولا مسارٌ ولا مجموعة: **الموضعُ داخلَ «مساحتي» فقط**. */
    $ANCHOR_ORDER = array('main/role_board.php' => -1000000, 'chats/index.php' => -999999);

    /* ── ① كلُّ بندٍ يُنسب: اسمُه · مجموعتُه · قسمُه · ترتيبُه ─────────────── */
    $rows = array();
    $covered = array();
    foreach ($items as $idx => $it) {
        $raw  = (string) $it['route'];
        $base = uxuiNavBaseRoute($raw);
        if ($base === '') { continue; }
        $covered[$base] = true;
        $isVariant = (strpbrk($raw, '#?') !== false); /* مدخلٌ ثانٍ مقصود — اسمُه له */
        $c   = isset($uxMap[$base]) ? $uxMap[$base] : null;
        $cur = isset($uxCurMap[$base]) ? $uxCurMap[$base] : null;

        /* الاسمُ بالترتيبِ السابقِ حرفًا — **هذه الجولةُ تُعيد التبويبَ لا التسمية**:
           المرساةُ/المنظرُ اسمُه له · والمعتمَدُ اسمَه المعياريّ · والمعلَّقُ اسمَه
           الحاليَّ من السجل · ومن لا قياسَ current لدورِه يبقى على اسمِ صفِّه.
           (وفرضُ المعياريِّ على الأخيرِ أسقط رابطًا: توأمانِ صارا باسمٍ واحدٍ
            فابتلع حارسُ التكرارِ ثانيَهما — قِيس في الدور ٢٤.) */
        if ($isVariant) { $name = $it['label_ar']; }
        elseif ($c && $c['status'] === 'APPROVED') { $name = $c['canonical_ar']; }
        elseif ($cur) { $name = $cur['cur_label']; }
        else { $name = $it['label_ar']; }
        /* ◆ **والمرساتانِ اسمُهما واحدٌ في كلِّ إدارة**: قرارُ المالكِ 2026-07-27
             «التسميةُ موحَّدةٌ — صفٌّ واحدٌ لكلِّ الأدوارِ باسمِ ‹الرئيسية›»، وأربعةُ
             أدوارٍ كانت تحمل اسمَ لوحتِها («لوحة مشرف اسطول» …) فتُقرأ صفحتين.
             وموضعٌ واحدٌ باسمين يناقض «المكانَ نفسَه دائمًا». */
        if (!$isVariant && isset($ANCHOR_LABEL[$base])) { $name = $ANCHOR_LABEL[$base]; }

        $section = $c ? (string) $c['group_name'] : ($cur ? (string) $cur['cur_group'] : '');
        if ($section === '— خارج التبويب') { $section = ''; }
        /* قسمٌ مفروضٌ يغلب عنوانَ المصفوفة — «ما ينتظرني» يجمع صناديقَ الاعتماد،
           و`'-'` تمحو القسمَ فيصعد الرابطُ إلى صدرِ مجموعتِه (عنوانُ مصفوفتِه
           من مجالٍ آخر فلا يصلح رأسًا فرعيًّا تحتَ هذه المجموعة). */
        $pinSec = ems_nav_pin_section($base);
        if ($pinSec === '-') { $section = ''; }
        elseif ($pinSec !== '') { $section = $pinSec; }
        /* ◆ **والقسمُ المُعلَنُ يغلب** (INJ-FRD-01 · XC-01): إن نشرت الإدارةُ
             جدولَها المستهدَفَ في `gov_target_nav` فمجموعتُه هناك هي القسمُ —
             وهي **لكلِّ دورٍ** فتُحمَل عليها المجموعاتُ الستُّ المستهدفةُ التي
             يعجز عنها رأسُ الطيِّ (مفتاحُه المسارُ لا الدور). ولا يسري هذا إلا
             على المُعلَن: من لا صفَّ له في الجدولِ يبقى على سلوكِه السابقِ حرفًا. */
        /* ⚠ **والمتغايرُ يفوز بالموضعِ فيحمل قسمَه القديمَ معه**: لـ
             `Operations/distribution_space.php` أربعةُ صفوفٍ (‏أساسٌ و`?view=`
             و`#2` و`#3`)، و`?view=` رتبتُه أصغرُ فيُصيَّر أوّلًا **ويبتلع حارسُ
             التكرارِ الأساسَ المُعلَن** — فظهر البندُ تحتَ «التخطيط وتخصيص
             الموارد» لا تحتَ المُعلَنِ «التخطيط والتوزيع».
           ⇒ **القسمُ والرتبةُ يسريان على المتغايرِ أيضًا** — فهويّةُ الشاشةِ
             مسارُها الأساس. ⛔ **والاسمُ وحدَه يبقى للمتغاير**: هو مدخلٌ ثانٍ
             مقصودٌ وله تسميتُه (وفرضُ الاسمِ المعياريِّ عليه أسقط رابطًا في
             الدور ٢٤ حين صار توأمان باسمٍ واحد). */
        $isDecl = 0;
        if (isset($declSec[$base])) { $section = $declSec[$base]['g']; $isDecl = 1; }
        /* والمرساتانِ تسبقان كلَّ شيءٍ داخلَ «مساحتي» — بلا قسمٍ وبأصغرِ ترتيب */
        if (!$isVariant && isset($ANCHOR_ORDER[$base])) { $section = ''; }
        $sort = $c ? (int) $c['sort_no'] : ($cur ? (int) $cur['cur_order'] : 999);
        /* والرتبةُ المُعلَنةُ تغلب `sort_no` — لأنَّ الأخيرَ مفتاحُه المسارُ لا الدور */
        if ($isDecl) { $sort = $declSec[$base]['o']; }
        if (!$isVariant && isset($ANCHOR_ORDER[$base])) { $sort = $ANCHOR_ORDER[$base]; }
        $lvl  = $c ? (int) $c['level_no'] : 0;

        /* ◆ **والبابُ المُعلَنُ يغلب تثبيتَ المسار**: `nav_route_group` مفتاحُه
             المسارُ فيصلح للمنصّةِ ولا يصلح لدورةِ إدارةٍ بعينِها. ولذلك ينتقل
             `Approvals/hours_approval.php` من «مساحتي» (‏تثبيتٌ `PIN` عامٌّ)
             إلى بابِ «الاعتماد والمطابقة» في الإدارةِ التي أعلنته — **ويبقى
             في «مساحتي» لكلِّ دورٍ لم يُعلن**. والعنوانُ الفرعيُّ يسقط لأنَّ
             الرأسَ صار يحمله. */
        if ($isDecl && isset($declHead[$declSec[$base]['n']])) {
            $code = $declHead[$declSec[$base]['n']];
            $section = '';
        }
        elseif (isset($rgMap[$base])) { $code = $rgMap[$base]; }
        else { list($code, ) = ems_nav_group_for_route($base, $lvl, $section); }
        if (!isset($tax[$code])) { $code = isset($tax['DAILY']) ? 'DAILY' : key($tax); }

        $rows[] = array('code' => $raw, 'name' => $name, 'icon' => $it['icon'],
                        'group' => $code, 'section' => $section, 'sort' => $sort,
                        'variant' => $isVariant ? 1 : 0, 'idx' => $idx, 'decl' => $isDecl);
    }

    /* ── ② ما يحمله السجلُّ ولا صفَّ تبعيةٍ له (الرئيسيةُ والمراسلاتُ لبعضِ
           الأدوار) يُصطنع رابطُه — كما كان قبلَ هذه الطبقة: صفرُ فقد ─────────
       ◆ **INJ-FIX-01 · GAP-19 — وثقبُ التعطيلِ هنا أيضًا**: `$items` نشطةٌ
         وحدَها، فـ`$covered` لا يُعلَّم بالمعطَّل. وهذه هي **دالةُ التصيير**
         نفسُها — فسدُّ الثقبِ في المُهيِّئِ وحدَه لا يكفي: قِيس أن ستةَ روابطَ
         معطَّلةٍ ظلَّت تُصيَّر بعدَ سدِّه هناك، ومصدرُها هذه الحلقة.
         ⇐ يُستثنى المعطَّلُ صراحةً — والاصطناعُ يبقى لما **لا صفَّ له إطلاقًا**. */
    /* ══ RPR-02 §٦ س٦ — **وثقبُ الصلاحيةِ من الشكلِ نفسِه** (2026-08-30) ══════
       ◆ **العطبُ المقيس**: `$items` لا تحمل إلّا ما **جاز فحصَ الصلاحيةِ** في
         `getUnifiedNavItems` (`permission_code IS NULL OR EXISTS role_permissions
         … can_view = 1`). فالبندُ الذي **مُنع بالصلاحيةِ** لا يُعلَّم في
         `$covered`، **فتعيد هذه الحلقةُ اصطناعَه من السجلّ** — ويظهر لمن لا
         منحَ له. ⇐ **وهو ثقبُ تجاوزٍ لا زلّةُ عرض**: فحصٌ يُلتَفُّ عليه من بابٍ
         ثانٍ، والاستثناءُ كان للمعطَّلِ (`active = 0`) وحدَه.
       ◆ **والمقيسُ عند الكشفِ حالةٌ واحدة**: دور ٢٨ · `Portal/my_tasks.php`
         (‏سُدَّ رمزُه في س٦ فمُنع، ثمَّ عاد من هنا) — ولا ثانيةَ لها في النظامِ
         كلِّه. ⛔ **وواحدةٌ تكفي**: القاعدةُ تُسدُّ لا الحالة.
       ⇒ **يُستثنى المُنَعُ بالصلاحيةِ كما يُستثنى المعطَّل** — والاصطناعُ يبقى
         لغرضِه: ما **لا صفَّ له إطلاقًا** (الثوابتُ الصلبةُ القديمة). */
    $navDisabled = array();
    if (isset($GLOBALS['__uxui_cur_role'])) {
        $__rid2 = (int) $GLOBALS['__uxui_cur_role'];
        $__dq2 = @mysqli_query($conn, "SELECT `route` FROM `nav_items`
                                        WHERE `role_id` = " . $__rid2 . "
                                          AND `active` = 0");
        while ($__dq2 && ($__dr2 = mysqli_fetch_assoc($__dq2))) {
            $navDisabled[uxuiNavBaseRoute($__dr2['route'])] = true;
        }
        $__pq2 = @mysqli_query($conn, "SELECT n.`route` FROM `nav_items` n
                                        WHERE n.`role_id` = " . $__rid2 . " AND n.`active` = 1
                                          AND n.`permission_code` IS NOT NULL AND n.`permission_code` <> ''
                                          AND NOT " . perm_nav_view_exists_sql('n'));
        while ($__pq2 && ($__pr2 = mysqli_fetch_assoc($__pq2))) {
            $navDisabled[uxuiNavBaseRoute($__pr2['route'])] = true;
        }
        /* والمُنَعُ **بالقالبِ** يُعلَّم كما يُعلَّم المُنَعُ بالمنحِ — الثقبُ
           نفسُه من البابِ الثالث: بندٌ رشَّحته طبقةُ القوالبِ أعلاه لا يجوز
           أن تعيدَ هذه الحلقةُ اصطناعَه من السجلّ. */
        $__tpl2 = unifiedNavTemplateState($conn);
        if ($__tpl2['covered']) {
            $__tq2 = @mysqli_query($conn, "SELECT n.`route`, m.`code` FROM `nav_items` n
                                            JOIN `modules` m ON m.`id` = n.`module_id`
                                            WHERE n.`role_id` = " . $__rid2 . " AND n.`active` = 1
                                              AND n.`permission_code` IS NOT NULL AND n.`permission_code` <> ''");
            while ($__tq2 && ($__tr2 = mysqli_fetch_assoc($__tq2))) {
                if (!isset($__tpl2['allowed'][(string) $__tr2['code']])) {
                    $navDisabled[uxuiNavBaseRoute($__tr2['route'])] = true;
                }
            }
        }
    }

    foreach ($uxCurMap as $base => $cur) {
        if (isset($covered[$base]) || !isset($uxMap[$base])) { continue; }
        if (isset($navDisabled[$base])) { continue; }   // معطَّلٌ بقرارٍ — لا يُصطنع
        $proper = $uxMap[$base]['route'];
        $icon = ($base === 'main/role_board.php') ? 'fa fa-house'
              : (($base === 'chats/index.php') ? 'fa fa-comments' : 'fa fa-link');
        $section = (string) $uxMap[$base]['group_name'];
        if ($section === '— خارج التبويب') { $section = ''; }
        $code = isset($rgMap[$base]) ? $rgMap[$base]
              : current(ems_nav_group_for_route($base, (int) $uxMap[$base]['level_no'], $section));
        if (!isset($tax[$code])) { $code = isset($tax['DAILY']) ? 'DAILY' : key($tax); }
        $nameSyn = $cur['cur_label'];
        $sortSyn = (int) $cur['cur_order'];
        /* والمرساتانِ هنا أيضًا: دورٌ صفُّه معطَّلٌ في `nav_items` تُولَّد له
           «الرئيسية» من السجل — ولو لم يُطبَّق الحكمُ هنا لظهرت خامسةً لا أولى
           (قِيس: الدوران ١٧ و٢٥ صفُّهما `active=0` فجاءت من هذا الفرع). */
        if (isset($ANCHOR_ORDER[$base])) {
            $section = '';
            $sortSyn = $ANCHOR_ORDER[$base];
            if (isset($ANCHOR_LABEL[$base])) { $nameSyn = $ANCHOR_LABEL[$base]; }
        }
        $rows[] = array('code' => $proper, 'name' => $nameSyn, 'icon' => $icon,
                        'group' => $code, 'section' => $section, 'sort' => $sortSyn,
                        'variant' => 0, 'idx' => 10000 + count($rows));
        $covered[$base] = true;
    }
    /* ── ②-ب مرساتا كلِّ سايدبار: «الرئيسية» و«المراسلات» ────────────────────
          كانتا تُحقنان في `printStageNav` لكلِّ دورٍ **لا يولّدهما سجلُّه**
          (الأدوارُ الفرعيةُ ومن خارجِ قياسِ current). ومسارُ العشرةِ لا يمرُّ
          بتلك الدالةِ — فلولا حقنُهما هنا لسقطتا عن أحدَ عشرَ دورًا صامتةً.
          **وهما مُثبَّتتان في «مساحتي»**: مكانٌ واحدٌ في كلِّ إدارةٍ لا يتغير. */
    /* RPR-W02 §٤-٣: المرساتان من `nav_canonical.anchor_key` — لا مسارَ حرفيًّا هنا. */
    require_once __DIR__ . '/nav_icon_map.php';
    require_once __DIR__ . '/nav_anchors.php';
    $anchors = '';
    $__aHome = ems_nav_anchor($conn, 'HOME');
    if ($__aHome !== null && !isset($covered[$__aHome['route']])) {
        ob_start();
        printNavLinkItem(array('code' => $__aHome['route'], 'name' => $__aHome['label'], 'icon' => $__aHome['icon']),
                         $basePrefix, $badges);
        $one = ob_get_clean();
        if (ems_nav_group_has_link($one)) { $anchors .= $one; $covered[$__aHome['route']] = true; }
    }
    $__aChat = ems_nav_anchor($conn, 'CHATS');
    if ($__aChat !== null && !isset($covered[$__aChat['route']])) {
        /* المراسلاتُ لها وسمُها الخاصُّ (مُعرِّفُ الرابطِ وشارةُ غيرِ المقروء) —
           فتُؤخذ حقنةُ `insidebar` إن جاءت، وإلا فالوسمُ المكافئُ من السجلِّ نفسِه. */
        ems_nav_mark_printed($__aChat['route'] . '||' . $__aChat['label']);
        $anchors .= ($afterHome !== '') ? $afterHome
            : ems_nav_anchor_li($conn, 'CHATS', $basePrefix, 'id="sidebarChatLink"',
                  '<span id="nav-unread-badge" class="nav-count-badge" style="display:none;"></span>');
        $covered[$__aChat['route']] = true;
    }
    /* حقنةُ المراسلاتِ الصلبةُ تُسقَط متى ولَّدها السجلُّ — منعًا للازدواج */
    $afterHome = '';

    if (empty($rows) && $anchors === '') { return false; }

    /* ── ③ الترتيب: المجموعةُ بترتيبِ التبويب · القسمُ بأصغرِ ترتيبٍ فيه ──── */
    $tree = array();
    foreach ($rows as $r) { $tree[$r['group']][$r['section']][] = $r; }

    $printed = false;
    $pending = array();  /* تُبنى المجموعاتُ كلُّها ثم تُطبع — لأن الفتحَ الافتراضيَّ
                            لا يُعرف إلا بعد معرفةِ أيُّها أكبرُ في هذه الإدارة */
    $anchorHome = isset($tax['MINE']) ? 'MINE' : key($tax); /* بيتُ المرساتين */
    foreach ($tax as $code => $meta) {
        $isAnchorHome = ($code === $anchorHome && $anchors !== '');
        if (empty($tree[$code]) && !$isAnchorHome) { continue; }
        $sections = isset($tree[$code]) ? $tree[$code] : array();
        foreach ($sections as $sname => &$list) {
            usort($list, function ($a, $b) {
                if ($a['sort'] !== $b['sort']) { return $a['sort'] - $b['sort']; }
                if ($a['variant'] !== $b['variant']) { return $a['variant'] - $b['variant']; }
                return $a['idx'] - $b['idx'];
            });
        }
        unset($list);
        uasort($sections, function ($a, $b) {
            $ma = PHP_INT_MAX; $mb = PHP_INT_MAX;
            foreach ($a as $r) { if ($r['sort'] < $ma) { $ma = $r['sort']; } }
            foreach ($b as $r) { if ($r['sort'] < $mb) { $mb = $r['sort']; } }
            if ($ma !== $mb) { return $ma - $mb; }
            return $a[0]['idx'] - $b[0]['idx'];
        });

        /* ── ④ الجسمُ أولًا ثم الغلاف — حارسُ الغلافِ الفارغ ───────────────
              `printNavLinkItem` قد تُسقط الرابطَ (حارسُ التكرارِ أو كابحُ
              المساحة)، فلا يُطبع عنوانُ قسمٍ بلا روابطَ تحته ولا رأسُ مجموعةٍ
              خلا من كلِّ روابطِها. */
        $bodies = array(); $liveSections = 0; $liveLinks = 0;
        foreach ($sections as $sname => $list) {
            $kept = array();
            foreach ($list as $r) {
                ob_start();
                printNavLinkItem(array('code' => $r['code'], 'name' => $r['name'], 'icon' => $r['icon']), $basePrefix, $badges);
                $one = ob_get_clean();
                if (ems_nav_group_has_link($one)) { $kept[] = array('r' => $r, 'html' => $one); }
            }
            if (empty($kept)) { continue; }
            $liveSections++; $liveLinks += count($kept);
            $bodies[] = array('name' => (string) $sname, 'kept' => $kept);
        }
        if ($isAnchorHome) { $liveLinks += substr_count($anchors, '<a '); }
        if ($liveLinks === 0) { continue; }

        /* ── ⑤ متى يُفيد عنوانُ القسمِ ومتى يكون ضجيجًا ────────────────────
              ◆ الحدُّ **تسعةٌ** لا رقمٌ مخترَع: ف٧-٢ يجعل التسعةَ حدَّ ما
                يُقرأ بلمحة. فما دونها يُقرأ **بلا عناوين** — وعنوانٌ لكلِّ
                رابطٍ يضاعف الأسطرَ ولا يضيف معنًى. وما بلغ عشرةً يلزمه بناءٌ.
              ◆ وفي المجموعةِ الطويلة: العنوانُ لمن تحتَه **رابطان فأكثر**،
                والوحيدُ يصعد إلى صدرِ المجموعةِ بلا عنوان — فلا يبدو تابعًا
                لعنوانٍ ليس له، ولا يُفتتح عنوانٌ لسطرٍ واحد. */
        /* ◆ **والمحوران**: ① عنوانُ الدورةِ المستنديةِ من المصفوفة · ② المجالُ
             من الدليل. الأولُ أدقُّ حين تكون المجموعةُ مجالًا واحدًا؛ والثاني
             أنفعُ في المجموعتين **العابرتين للمجالات** («التقارير» و«البيانات
             والإعدادات») حيث يكون العنوانُ الأولُ مفردًا لكلِّ رابطٍ تقريبًا
             فلا يبني شيئًا (قِيس: ثلاثةَ عشرَ سطرًا بلا بناءٍ في الدور ١٩).
           ◆ وما بقي فوقَ التسعةِ بالمحورِ الأولِ يُعاد تقسيمُه بالثاني والعكس. */
        $flat = array();
        foreach ($bodies as $B) { foreach ($B['kept'] as $k) { $flat[] = $k; } }
        /* ◆ **والقسمُ المُعلَنُ يتخطّى عتبةَ التسعة** (INJ-FRD-01 · XC-01):
             عتبةُ `> 9` قاعدةُ قراءةٍ تمنع تفتيتَ المجموعةِ القصيرةِ بعناوين.
             لكنَّ المرجعَ الحاكمَ يشترط ظهورَ المجموعاتِ الستِّ المستهدفةِ
             نصًّا، ومجموعاتُ الإدارتَين ستةٌ وثمانيةُ روابطَ — فتُدفن العتبةُ
             ما يطلبه المتطلَّب. **فالإعلانُ يرفع العتبةَ لمجموعتِه وحدَها**،
             ومن لا إعلانَ له تبقى عتبتُه كما كانت حرفًا. */
        $hasDecl = false;
        foreach ($flat as $k) { if (!empty($k['r']['decl'])) { $hasDecl = true; break; } }
        $useSubheads = ($liveLinks > 9) || $hasDecl;
        $lead = $flat; $headed = array();

        /* ── ⑤-أ قسمٌ مفروضٌ يظهر عنوانُه ولو قصُرت المجموعة ─────────────────
              «ما ينتظرني» أعلى مقاصدِ السايدبار تكرارًا؛ ولو دُفن بين الروابطِ
              الشخصيةِ بلا فاصلٍ لضاع أثرُ دمجِه في «مساحتي». فيُفصَل دائمًا. */
        $forced = ems_nav_forced_sections();
        $forcedOut = array();
        if (!empty($forced)) {
            $rest = array();
            foreach ($bodies as $B) {
                if (isset($forced[$B['name']])) { $forcedOut[] = $B; } else { $rest[] = $B; }
            }
            if (!empty($forcedOut)) {
                $bodies = $rest;
                $flat = array();
                foreach ($bodies as $B) { foreach ($B['kept'] as $k) { $flat[] = $k; } }
                $lead = $flat;
                $liveLinks = count($flat);
                foreach ($forcedOut as $B) { $liveLinks += count($B['kept']); }
                $hasDecl = false;
                foreach ($flat as $k) { if (!empty($k['r']['decl'])) { $hasDecl = true; break; } }
                $useSubheads = ($liveLinks > 9) || $hasDecl;
            }
        }

        if ($useSubheads && !empty($bodies)) {
            $crossCut = ($code === 'REPORTS' || $code === 'SETUP');
            $byDomain  = function ($k) use ($tax, $code) {
                $dc = ems_nav_domain_for_route(uxuiNavBaseRoute($k['r']['code']));
                return ($dc === $code || !isset($tax[$dc])) ? '' : $tax[$dc]['name_ar'];
            };
            $bySection = function ($k) { return (string) $k['r']['section']; };
            /* ◆ **والمُعلَنُ يغلب محورَ المجال**: المجموعتانِ العابرتان
                 («التقارير» و«الإعدادات») تُقسَّمان بالمجالِ لا بالقسم، لأن
                 عنوانَ القسمِ فيهما مفردٌ لكلِّ رابطٍ تقريبًا فلا يبني شيئًا.
                 لكنَّ الرابطَ **المُعلَنَ** له مجموعةٌ يشترطها المرجعُ نصًّا،
                 فيُقسَّم بها هو ويبقى المجالُ محورًا لما سواه. (وبدونِ هذا
                 قِيس أن «البيانات المرجعية» تظهر تحتَ «العقود والعملاء» —
                 مجالُها — وهو ضدُّ SAL-21 حرفًا.) */
            $primary = function ($k) use ($crossCut, $byDomain, $bySection) {
                if (!empty($k['r']['decl'])) { return $bySection($k); }
                return $crossCut ? $byDomain($k) : $bySection($k);
            };
            $second  = $crossCut ? $bySection : $byDomain;

            /* دلوٌ يُبنى بمفتاحٍ ثم يُنقّى: المفردُ وبلا اسمٍ يصعد إلى الصدرِ المكشوف */
            /* ◆ **والمُعلَنُ المفردُ يبقى عنوانًا**: القاعدةُ العامةُ ترفع الدلوَ
                 المفردَ إلى الصدرِ المكشوف (عنوانٌ لرابطٍ واحدٍ ضجيجٌ لا بناء).
                 لكنَّ مجموعةً مستهدَفةً برابطٍ واحدٍ — «الأداءُ والمطالباتُ
                 التجارية» للدورِ ١٢ مثلًا — **مجموعةٌ يشترطها المرجعُ نصًّا**،
                 ورفعُها يُسقط اسمَها من القائمةِ فيُقاس نقصٌ حيث لا نقص. */
            $bucketize = function ($items, $key) {
                $b = array();
                foreach ($items as $k) { $b[$key($k)][] = $k; }
                $ld = array(); $out = array();
                foreach ($b as $n => $ks) {
                    $declared = false;
                    foreach ($ks as $k) { if (!empty($k['r']['decl'])) { $declared = true; break; } }
                    if ($n === '' || (count($ks) < 2 && !$declared)) { foreach ($ks as $k) { $ld[] = $k; } }
                    else { $out[] = array('name' => (string) $n, 'kept' => $ks); }
                }
                return array($ld, $out);
            };

            list($lead, $headed) = $bucketize($flat, $primary);
            if (count($lead) > 9) {
                list($ld2, $more) = $bucketize($lead, $second);
                if (!empty($more)) { $lead = $ld2; $headed = array_merge($headed, $more); }
            }
            $refined = array();
            foreach ($headed as $H) {
                if (count($H['kept']) <= 9) { $refined[] = $H; continue; }
                list($subLead, $subs) = $bucketize($H['kept'], $second);
                if (empty($subs)) { $refined[] = $H; continue; }
                foreach ($subs as $S) { $refined[] = $S; }
                if (!empty($subLead)) { $refined[] = array('name' => $H['name'], 'kept' => $subLead); }
            }
            /* عنوانان بالاسمِ نفسِه يُدمجان — وإلا قُرئ العنوانُ مرتين في مجموعة */
            $headed = array();
            foreach ($refined as $H) {
                $hit = -1;
                foreach ($headed as $i => $E) { if ($E['name'] === $H['name']) { $hit = $i; break; } }
                if ($hit >= 0) { $headed[$hit]['kept'] = array_merge($headed[$hit]['kept'], $H['kept']); }
                else { $headed[] = $H; }
            }
            if (empty($headed)) { $useSubheads = false; $lead = $flat; }
        }

        ob_start();
        if ($isAnchorHome) { echo $anchors; $anchors = ''; } /* «الرئيسية» أولَ ما يُرى */
        foreach ($lead as $k) { echo $k['html']; }
        foreach ($forcedOut as $B) {   /* القسمُ المفروضُ يلي الصدرَ المكشوف */
            echo '<li class="nav-subhead" aria-hidden="true"><span>'
               . htmlspecialchars($B['name'], ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
            foreach ($B['kept'] as $k) { echo $k['html']; }
        }
        if ($useSubheads) {
            foreach ($headed as $B) {
                echo '<li class="nav-subhead" aria-hidden="true"><span>'
                   . htmlspecialchars($B['name'], ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
                foreach ($B['kept'] as $k) { echo $k['html']; }
            }
        }
        $body = ob_get_clean();

        $key  = 'g-' . strtolower($code);
        $open = ((int) $meta['open_default'] === 1);
        $nm   = htmlspecialchars($meta['name_ar'], ENT_QUOTES, 'UTF-8');

        /* ── ⑥ **لا رابطَ خارجَ مجموعة** (قرارُ المالك 2026-08-17) ─────────────
              جُرِّبت مرحلةً طباعةُ المجموعةِ النحيفةِ (رابطٌ أو رابطان) مكشوفةً
              بلا رأسِ طيٍّ توفيرًا للنقرة — **ورُدَّت بنصِّ المالك**: «لا تجعل
              رابطًا خارجَ مجموعة». والقاعدةُ الآن قاطعة: **كلُّ رابطٍ يسكن
              مجموعةً برأسٍ وأيقونةٍ واسم**، مهما قلَّ عددُه. فالقائمةُ تُقرأ
              بالبنيةِ نفسِها في كلِّ إدارة، ولا يطفو سطرٌ بلا نسب. */
        $total = 0;
        foreach ($bodies as $B) { foreach ($B['kept'] as $k) { $total += isset($badges[$k['r']['code']]) ? intval($badges[$k['r']['code']]) : 0; } }

        $pending[] = array(
            'solo' => false, 'code' => $code, 'key' => $key, 'name' => $nm,
            'icon' => $meta['icon'], 'body' => $body, 'links' => $liveLinks,
            'badge' => $total, 'home' => $isAnchorHome, 'open' => $open,
        );
    }

    /* ══ ⑦ الفتحُ الافتراضيُّ يتبع الإدارةَ لا قائمةً عامة ══════════════════
       ◆ **المقيس**: كان المفتوحُ ابتداءً ثلاثةَ رموزٍ ثابتةٍ للجميع، فـ**تسعُ
         إداراتٍ من تسعَ عشرةَ تفتح على مجموعةٍ ليست عملَها**: إدارةُ المالية
         تُستقبَل بمجموعةٍ فيها ٣٨ رابطًا **مطويّة**، والصلاحياتُ بـ٢٢، والمراجعُ
         الداخليُّ بـ٢٠. والافتراضُ الذي لا يعرف صاحبَه يُكلّفه نقرةً كلَّ يوم.
       ◆ **والقاعدةُ الآن**: «مساحتي» مفتوحةٌ دائمًا (فيها «ما ينتظرني»)، و**أكبرُ
         مجموعةٍ في هذه الإدارةِ بعينِها** مفتوحةٌ معها، وما عداهما مطويّ.
         فيفتح كلُّ دورٍ على ما يعمل فيه — بلا تغييرِ ترتيبٍ ولا موضع.
       ◆ **ولا يُمَسُّ اختيارُ المستخدم**: `insidebar.php` يحفظ المفتوحَ في
         `localStorage`، فهذا افتراضُ **أولِ زيارةٍ** فقط ومن طوى بقيت مطويّةً له. */
    $openCode = null; $openMax = -1;
    foreach ($pending as $P) {
        if (!empty($P['home']) || $P['code'] === $anchorHome) { continue; }
        if ($P['links'] > $openMax) { $openMax = $P['links']; $openCode = $P['code']; }
    }

    foreach ($pending as $P) {
        /* ══ أوّلُ دخولٍ: **كلُّ المجموعاتِ مطويّة** (قرارُ المالك 2026-08-17) ══
           ◆ كانت القاعدةُ أعلاه تفتح اثنتين: «مساحتي» دائمًا، ومعها أكبرُ
             مجموعةٍ في إدارةِ الدور. والمقيسُ في المُصيَّر: مجموعتان مفتوحتان
             لكلِّ دورٍ جُرِّب (محمد · مصعب · مبيعات · اروينا).
           ◆ **والقرارُ الآن**: يفتح النظامُ على قائمةٍ مطويّةٍ بالكامل، ثم
             يفتح المستخدمُ ما يريد — ونقرتُه تطوي سواها (أكورديون)، فلا تُفتح
             أكثرُ من واحدةٍ في أيِّ لحظة.
           ◆ **والثمنُ مُعلَنٌ لا مسكوتٌ عنه**: هذا يعكس حكمَ INJ-0527 الذي
             فُتحت المجموعاتُ لأجله — «المستخدمُ الجديدُ يفتح النظامَ فلا يرى
             رابطًا واحدًا حتى يضغط». فمن يدخل أوّلَ مرّةٍ يرى رؤوسَ المجموعاتِ
             وحدَها، وعليه نقرةٌ ليصل أوّلَ رابط. وهذا هو المطلوبُ صراحةً.
           ◆ **ولا يُمَسُّ اختيارُ العائد**: `insidebar.php` يحفظ ما فُتح في
             `localStorage`، فهذا افتراضُ أوّلِ زيارةٍ وحدَها. ولإعادةِ القديم:
             أعِدْ سطرَ الحسابِ الملغى أدناه.
           ◆ والحسابُ أعلاه (`$openCode`) تُرك كما هو: يبقى مُعبِّرًا عن «أكبرِ
             مجموعةٍ في الإدارة» لو أُعيد الفتحُ الافتراضيُّ يومًا. */
        // $open = (!empty($P['home']) || $P['code'] === $anchorHome || $P['code'] === $openCode);
        $open = false;
        $badge = $P['badge'] > 0
            ? ' <span class="nav-count-badge nav-group-badge">' . ($P['badge'] > 99 ? '99+' : $P['badge']) . '</span>' : '';
        echo '<li class="nav-group' . ($open ? ' open' : '') . '" data-group-key="' . $P['key'] . '">' . "\n";
        /* الاسمُ على الزرِّ نفسِه لا في `span` وحدَه — طيُّ الشريطِ يُخفي الـspan
           فيبقى الزرُّ بلا اسمٍ لقارئِ الشاشة (WCAG 2.2 AA · 4.1.2). */
        echo '  <button type="button" class="nav-group-head" aria-expanded="' . ($open ? 'true' : 'false') . '"'
           . ' aria-controls="navgrp-' . $P['key'] . '" aria-label="' . $P['name'] . '" title="' . $P['name'] . '">'
           . '<i class="' . htmlspecialchars($P['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i> '
           . '<span class="nav-group-name">' . $P['name'] . '</span>' . $badge
           . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
        echo '  <ul class="nav-group-items" id="navgrp-' . $P['key'] . '">' . "\n";
        echo $P['body'];
        echo '  </ul>' . "\n" . '</li>' . "\n";
        $printed = true;
    }
    /* دورٌ لم تُطبع له «مساحتي» (لا يقع اليوم — محروسٌ باختبار): لا تُفقد المرساتان */
    if ($anchors !== '') { echo $anchors; $printed = true; }
    return $printed;
}

function renderUnifiedNavigationV2($conn, $roleId, $basePrefix = '../', $badges = array(), $afterHome = '') {
    $items = getUnifiedNavItems($conn, $roleId);
    if (empty($items)) { return false; }
    emsNavLandingAnchors($items);

    /* ══ العشرُ مجموعاتٍ (نصُّ المالك 2026-08-17) ═══════════════════════════
       متى بُذر التبويبُ صار **مسارُ الطباعةِ واحدًا**: كلُّ البنودِ — المعتمَدُ
       والمعلَّقُ وما بقي بأبوابِه ومراحلِه — تُنسب إلى عشرِ مجموعاتٍ وتُطبع مرةً
       واحدة، فلا يُفتح للمجموعةِ الواحدةِ غلافان. وما دون البذرِ (أو
       `EMS_NAV_TEN=off`) يسلك المسارَ السابقَ أدناه **حرفًا بحرف**. */
    $tenTax = emsNavTaxonomy($conn);
    if (!empty($tenTax)) {
        $GLOBALS['__uxui_cur_role'] = (int) $roleId;
        /* ── NAVR: حارسُ السقوطِ الكلّيّ — BUSINESS_WORKSPACE_GLOBAL_FALLBACK ──
           دورٌ مربوطٌ بمساحةٍ لها مواضعُ MENU_ITEM ولا بندَ من بنودِه يقاطعها
           ⇒ سيُصيَّر بالتصنيفِ العامِّ وهو عينُ السقوطِ الممنوع: يُقيَّد
           Finding، وفي بيئةِ الاختبارِ (`EMS_NAV_STRICT=on`) يُعلَن مرئيًّا. */
        list($navrPl, $navrWs) = navrPlacementSections($conn, (int) $roleId);
        if ($navrWs !== null && !empty($navrPl)) {
            $navrHit = 0;
            foreach ($items as $navrIt) {
                if (isset($navrPl[uxuiNavBaseRoute((string) $navrIt['route'])])) { $navrHit++; }
            }
            if ($navrHit === 0) {
                navrRecordFinding($conn, 'GLOBAL_FALLBACK', (int) $roleId, $navrWs,
                    'مواضع المساحة موجودة وصفر بند من بنود الدور يقاطعها، سقوط للتصنيف العام');
                if (function_exists('ems_env') && strtolower((string) ems_env('EMS_NAV_STRICT', 'off')) === 'on') {
                    /* بلا تشكيلٍ ولا رمزٍ ولا مفردةٍ تقنيّةٍ — نصُّ الشاشةِ الحيّةِ محكومٌ
                       بسقّاطتَي UI-01/UI-02، والكشفُ الكاملُ في سجلِّ كشوفِ الملاحة */
                    echo '<li class="nav-group-head" style="color:#b00020;font-weight:bold;">'
                       . 'تعذر عرض ملاحة المساحة - سجل كشف معماري لهذا الدور</li>';
                }
            }
        }
        return printEmsTenGroupNav(
            $conn, $items, uxuiCanonicalMap($conn), uxuiCurrentMap($conn, (int) $roleId),
            $basePrefix, $badges, $afterHome
        );
    }

    /* ── UXUI-01 v3 (تفويض 2026-08-18): المصفوفةُ وحدَها مصدرُ التنقل ──
       APPROVED ⇐ الكتلةُ المعيارية · PENDING_OWNER وPENDING_OWNER_MERGE ⇐
       موضعُهما الحاليُّ من nav_canonical_current (لا من إرثِ link_groups) ·
       وما لا قياسَ current لدورِه (الأدوارُ الفرعية) أو لا صفَّ له يسلك
       المسارَ القائمَ كما هو. الترتيب: المعلَّقُ المسطّحُ («الرئيسية» أولَ
       ما يُرى) ⇐ القائمُ للفرعيةِ ⇐ المراسلاتُ ⇐ المعياريُّ ⇐ مجموعاتُ المعلَّق. */
    $uxMap = uxuiCanonicalMap($conn);
    $uxItems = array();          // APPROVED — الكتلةُ المعيارية
    $uxPendItems = array();      // المعلَّقُ بموضعِه الحاليِّ من السجل
    $uxCurMap = array();
    $GLOBALS['__uxui_cur_role'] = (int) $roleId; /* تعرفه حقنةُ printStageNav فلا تزدوج */
    if (!empty($uxMap)) {
        $uxCurMap = uxuiCurrentMap($conn, (int) $roleId);
        $rest = array(); $covered = array();
        foreach ($items as $it) {
            $base = uxuiNavBaseRoute($it['route']);
            if (!isset($uxMap[$base])) { $rest[] = $it; continue; }
            $covered[$base] = true;
            $st = $uxMap[$base]['status'];
            if ($st === 'APPROVED') { $uxItems[] = $it; }
            elseif (!empty($uxCurMap)) { $uxPendItems[] = $it; }
            else { $rest[] = $it; } /* دورٌ بلا قياسِ current — سلوكُه القائمُ حرفًا */
        }
        /* ما كان **ثابتًا صلبًا** في المصيِّرِ القديم (الرئيسية · المراسلات …) لا صفَّ
           تبعيةٍ له في nav_items لبعضِ الأدوار — والسجلُّ الحيُّ current يحمله.
           «لا سايدبارَ موروثٌ خلفَ المصفوفة»: يُصطنع رابطُه من السجلِّ نفسِه،
           بحرفِ مسارِه المعياريِّ (لا الصغيرِ) — وحارسُ التوأمِ يمنع أيَّ ازدواج. */
        /* ══ INJ-FIX-01 · GAP-19 — ثقبُ التعطيل ══════════════════════════════
           ◆ **العطبُ المقيس (٢٢ مسارًا)**: `$items` لا يحمل إلا الصفوفَ
             **النشطة**، فـ`$covered` لا يُعلَّم إلا بها. والمسارُ الذي **صفُّه
             موجودٌ ومعطَّلٌ عمدًا** (`active = 0`) لا يُعَدُّ مغطًّى — فتُعيد هذه
             الحلقةُ اصطناعَه من السجلِّ بأيقونةِ `fa fa-link`.
             ⇐ **فتعطيلُ الرابطِ لا يُخفيه**: يعود من بابٍ آخر، ومن عطَّله يظنُّه
               مُزالًا. وهذا نقضٌ لقرارِ تعطيلٍ لا زلَّةُ عرض.
           ◆ **والفرقُ الذي يجب أن تحفظه الحلقة**: الاصطناعُ لمسارٍ **لا صفَّ له
             إطلاقًا** (تسعةٌ مقيسة — الثوابتُ الصلبةُ القديمة)، لا لمسارٍ له صفٌّ
             أُطفئ بقرار. فيُستثنى المعطَّلُ صراحةً ويبقى الاصطناعُ لغرضِه. */
        $uxDisabled = array();
        $__dq = @mysqli_query($conn,
            "SELECT `route` FROM `nav_items` WHERE `role_id` = " . (int) $roleId . " AND `active` = 0");
        while ($__dq && ($__dr = mysqli_fetch_assoc($__dq))) {
            $uxDisabled[uxuiNavBaseRoute($__dr['route'])] = true;
        }

        foreach ($uxCurMap as $base => $cur) {
            if (isset($covered[$base]) || !isset($uxMap[$base])) { continue; }
            if (isset($uxDisabled[$base])) { continue; }   // معطَّلٌ بقرارٍ — لا يُصطنع
            $properRoute = $uxMap[$base]['route'];
            $icon = ($base === 'main/role_board.php') ? 'fa fa-home'
                  : (($base === 'chats/index.php') ? 'fa fa-comments' : 'fa fa-link');
            $synth = array('route' => $properRoute, 'label_ar' => $cur['cur_label'], 'icon' => $icon);
            if ($uxMap[$base]['status'] === 'APPROVED') { $uxItems[] = $synth; } else { $uxPendItems[] = $synth; }
        }
        /* حقنةُ المراسلاتِ الخامُ (afterHome من insidebar) لا تمرُّ بحارسِ التوأم —
           فمتى ولَّدها السجلُّ لهذا الدورِ تُسقَط الحقنةُ منعًا للازدواج. */
        if (isset($uxCurMap['chats/index.php'])) { $afterHome = ''; }
        $items = $rest;
        /* المعلَّقُ المسطّح (— خارج التبويب): «الرئيسية» وأخواتُها أولَ القائمة */
        printUxuiCurrentNav($uxPendItems, $uxCurMap, $basePrefix, $badges, 'flat');
    }

    // NAV-09: للدور المولَّد (مجموعاتٌ مرحلية) وضعُ المراحل يلغي كرومَ الأبواب،
    // وما بقي بلا مرحلةٍ (ثوابتُ قديمة) يُطبع بأبوابه بعده.
    $staged = array(); $doored = array();
    foreach ($items as $it) {
        if ($it['stage_no'] !== null) { $staged[] = $it; } else { $doored[] = $it; }
    }
    if (!empty($staged)) {
        // afterHome (رابط المراسلات الثابت) لا يُطبع في الوضع المرحلي —
        // فالمراسلاتُ صارت الرابطَ الثاني داخل أول مرحلة (قرار المالك 2026-08-03)
        printStageNav($roleId, $staged, $basePrefix, $badges);
        if (empty($doored)) {
            printUxuiCanonicalNav($uxItems, $uxMap, $basePrefix, $badges);
            printUxuiCurrentNav($uxPendItems, $uxCurMap, $basePrefix, $badges, 'groups');
            return true;
        }
        $items = $doored; $afterHome = '';
    }

    $byDoor = array();
    foreach ($items as $it) { $byDoor[$it['door']][] = $it; }
    $injected = false;
    foreach (unifiedNavDoors() as $doorKey => $meta) {
        if (empty($byDoor[$doorKey])) { continue; }
        if (!empty($meta['flat'])) {
            // بابٌ مسطّح: عناصره تُطبع مباشرةً بلا رأس طيٍّ («الرئيسية» أول ما يُرى)
            foreach ($byDoor[$doorKey] as $it) {
                printNavLinkItem(array('code' => $it['route'], 'name' => $it['label_ar'], 'icon' => $it['icon']), $basePrefix, $badges);
            }
            if ($afterHome !== '' && !$injected) { echo $afterHome; $injected = true; }
            continue;
        }
        printUnifiedNavDoor($doorKey, $meta, $byDoor[$doorKey], $basePrefix, $badges);
    }
    // دورٌ بلا باب HOME (لا يقع اليوم — محروسٌ باختبار): لا تُفقد الثوابت
    if ($afterHome !== '' && !$injected) { echo $afterHome; }
    // UXUI-01 v3: المعياريُّ (APPROVED) ثم مجموعاتُ المعلَّقِ بموضعِها الحاليِّ من السجل
    printUxuiCanonicalNav($uxItems, $uxMap, $basePrefix, $badges);
    printUxuiCurrentNav($uxPendItems, $uxCurMap, $basePrefix, $badges, 'groups');
    return true;
}
