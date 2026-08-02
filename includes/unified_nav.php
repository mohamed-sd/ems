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
                   n.counter_source, g.name AS group_name
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
    $hasFin = false;
    foreach ($items as $it) { if ($it['door'] === 'FIN') { $hasFin = true; break; } }
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
            $items = array_values(array_filter($items, function ($it) { return $it['door'] !== 'FIN'; }));
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
        if ($gname !== '') {
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
function renderUnifiedNavigationV2($conn, $roleId, $basePrefix = '../', $badges = array(), $afterHome = '') {
    $items = getUnifiedNavItems($conn, $roleId);
    if (empty($items)) { return false; }
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
