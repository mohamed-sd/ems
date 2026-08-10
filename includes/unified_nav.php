<?php
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
 * عناصر الدور الظاهرة: تبعيةٌ (active=1) × صلاحية (can_view=1 أو بلا فحص).
 * استعلامٌ واحد — الرابطُ الميت مستبعدٌ بنيويًّا لا برأي الشاشة.
 */
function getUnifiedNavItems($conn, $roleId) {
    $roleId = intval($roleId);
    $sql = "SELECT n.door, n.group_id, n.label_ar, n.route, n.icon, n.sort_order,
                   n.counter_source, g.name AS group_name,
                   g.stage_no, g.stage_title, g.display_order AS group_order
            FROM nav_items n
            LEFT JOIN link_groups g ON g.id = n.group_id AND g.is_active = 1
            WHERE n.role_id = {$roleId} AND n.active = 1
              AND (
                    n.permission_code IS NULL
                    OR EXISTS (
                        SELECT 1 FROM role_permissions p
                        WHERE p.module_id = n.module_id AND p.role_id = n.role_id AND p.can_view = 1
                    )
                  )
            ORDER BY FIELD(n.door,'HOME','DAILY','APPR','REC','REP','GOV','FIN','SET'), n.sort_order, n.id";
    $items = array();
    $res = mysqli_query($conn, $sql);
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { $items[] = $row; } }

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

    echo '<li class="nav-group" data-group-key="' . $key . '">' . "\n";
    echo '  <button type="button" class="nav-group-head" aria-expanded="false" aria-controls="navgrp-' . $key . '">'
       . '<i class="' . $icon . '"></i> <span class="nav-group-name">' . $name . '</span>' . $badge
       . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
    echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";

    // NAV-01 v6 §4 (update0007-ب): التكدّسُ يُدار بأدوات التعقيد — المجموعةُ
    // الكثيفةُ (> 12) تعرض أولَها ويطوي «المزيدُ» بقيتَها قابلةً للفتح،
    // «فلا تظهر المستوياتُ كلُّها دفعةً واحدة» — بلا حذفِ رابطٍ ولا تغييرِ بنية.
    $byGroup = array();
    foreach ($items as $it) {
        $g = isset($it['group_name']) && $it['group_name'] !== null ? $it['group_name'] : '';
        $byGroup[$g][] = $it;
    }
    static $moreSeq = 0;
    foreach ($byGroup as $gname => $gItems) {
        // UI-DEF-05: مجموعةٌ برابطٍ واحدٍ يحمل اسمَها نفسَه كانت تطبع النصَّ مرتين
        // (رأسًا ثم رابطًا) فتُقرأ تكرارًا — الرأسُ يسقط والرابطُ يبقى.
        $soloSameName = (count($gItems) === 1 && trim((string) $gItems[0]['label_ar']) === trim((string) $gname));
        if ($gname !== '' && !$soloSameName) {
            echo '<li class="nav-subhead" aria-hidden="true"><span>'
               . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
        }
        $dense = count($gItems) > 12;
        foreach ($gItems as $idx => $it) {
            if ($dense && $idx === 7) {
                $moreSeq++;
                $rest = count($gItems) - 7;
                echo '<li class="nav-more-toggle"><button type="button" class="nav-group-head" '
                   . 'style="font-size:.85em;opacity:.8" '
                   . 'onclick="var m=document.getElementById(\'navmore-' . $moreSeq . '\');'
                   . 'var open=m.style.display!==\'none\';m.style.display=open?\'none\':\'block\';'
                   . 'this.querySelector(\'span\').textContent=open?\'المزيد (' . $rest . ') ▾\':\'أقل ▴\';">'
                   . '<span>المزيد (' . $rest . ') ▾</span></button></li>' . "\n";
                echo '<li><ul id="navmore-' . $moreSeq . '" style="display:none;list-style:none;padding:0;margin:0">' . "\n";
            }
            printNavLinkItem(array(
                'code' => $it['route'],
                'name' => $it['label_ar'],
                'icon' => $it['icon'],
            ), $basePrefix, $badges);
        }
        if ($dense) { echo '</ul></li>' . "\n"; }
    }

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
        $title = trim((string) $sItems[0]['stage_title']);
        if ($title === '') { $title = $stageNo === 0 ? 'اللوحة والمساحة' : "المرحلة $stageNo"; }
        $key = 'stage-' . $roleId . '-' . $stageNo;
        // قرارُ المالك (2026-08-02) يخصص حكمَ ١٣: البدءُ مطويًّا بالكامل —
        // والوصولُ محفوظٌ بأكورديون «مجموعةٌ واحدةٌ مفتوحة» في insidebar.
        $openDefault = false;
        $icon = ems_nav_stage_icon($stageNo, $title); // أيقونة معبِّرة من عنوان المرحلة لا واحدة موحّدة

        $total = 0;
        foreach ($sItems as $it) { if (isset($badges[$it['route']])) { $total += intval($badges[$it['route']]); } }
        $badge = $total > 0 ? ' <span class="nav-count-badge nav-group-badge">' . ($total > 99 ? '99+' : $total) . '</span>' : '';

        echo '<li class="nav-group" data-group-key="' . $key . '">' . "\n";
        echo '  <button type="button" class="nav-group-head" aria-expanded="false"'
           . ' aria-controls="navgrp-' . $key . '">'
           . '<i class="' . $icon . '"></i> <span class="nav-group-name">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>' . $badge
           . '<i class="fa fa-chevron-down nav-group-caret" aria-hidden="true"></i></button>' . "\n";
        echo '  <ul class="nav-group-items" id="navgrp-' . $key . '">' . "\n";

        // ── رأسا كل إدارة (قرار المالك 2026-08-03): «الرئيسية» تؤدي دائمًا
        // إلى لوحة الإدارة المعنية (مسلك role_board يحلّها بالدور)، وتحتها
        // «المراسلات» بشارة غير المقروء الحية (يحدّثها مستطلِع insidebar
        // القائم عبر #nav-unread-badge) — أولَ رابطين في أول مرحلة، على
        // مستوى المصيّر فيصمدان أمام كل إعادة توليدٍ من الوثيقة.
        if (!$hdrPrinted) {
            $hdrPrinted = true;
            echo '<li><a href="' . $basePrefix . 'main/role_board.php">'
               . '<i class="fa fa-house"></i> <span>الرئيسية</span></a></li>' . "\n";
            echo '<li><a href="' . $basePrefix . 'chats/index.php" id="sidebarChatLink">'
               . '<i class="fa fa-comments"></i> <span>المراسلات</span>'
               . '<span id="nav-unread-badge" class="nav-count-badge" style="display:none;"></span>'
               . '</a></li>' . "\n";
        }

        $byGroup = array();
        foreach ($sItems as $it) { $byGroup[(string) $it['group_name']][] = $it; }
        static $stMoreSeq = 1000;
        foreach ($byGroup as $gname => $gItems) {
            // UI-DEF-05 نفسه في وضع المراحل: رأسٌ يطابق رابطَه الوحيد لا يُطبع.
            $soloSameName = (count($gItems) === 1 && trim((string) $gItems[0]['label_ar']) === trim((string) $gname));
            if ($gname !== '' && count($byGroup) > 1 && !$soloSameName) {
                echo '<li class="nav-subhead" aria-hidden="true"><span>'
                   . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
            }
            $dense = count($gItems) > 12; // أداةُ التكدس نفسُها (NAV-01 §4)
            foreach ($gItems as $idx => $it) {
                if ($dense && $idx === 7) {
                    $stMoreSeq++;
                    $rest = count($gItems) - 7;
                    echo '<li class="nav-more-toggle"><button type="button" class="nav-group-head" '
                       . 'style="font-size:.85em;opacity:.8" '
                       . 'onclick="var m=document.getElementById(\'navmore-' . $stMoreSeq . '\');'
                       . 'var open=m.style.display!==\'none\';m.style.display=open?\'none\':\'block\';'
                       . 'this.querySelector(\'span\').textContent=open?\'المزيد (' . $rest . ') ▾\':\'أقل ▴\';">'
                       . '<span>المزيد (' . $rest . ') ▾</span></button></li>' . "\n";
                    echo '<li><ul id="navmore-' . $stMoreSeq . '" style="display:none;list-style:none;padding:0;margin:0">' . "\n";
                }
                printNavLinkItem(array('code' => $it['route'], 'name' => $it['label_ar'], 'icon' => $it['icon']), $basePrefix, $badges);
            }
            if ($dense) { echo '</ul></li>' . "\n"; }
        }
        echo '  </ul>' . "\n";
        echo '</li>' . "\n";
    }
}

function renderUnifiedNavigationV2($conn, $roleId, $basePrefix = '../', $badges = array(), $afterHome = '') {
    $items = getUnifiedNavItems($conn, $roleId);
    if (empty($items)) { return false; }

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
        if (empty($doored)) { return true; }
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
    return true;
}
