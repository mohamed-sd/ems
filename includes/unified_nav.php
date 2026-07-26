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

/** الأبواب الستة الثابتة (UX-00 §6) بترتيبها وأيقوناتها.
 * HOME هنا للوحات الإدارات القائمة كشاشات (لوحة المدير المالي · المشتريات ·
 * الرحلات) — تظهر قبل الأبواب لا داخل مجموعةٍ مطوية، فرابط اللوحة أول ما يُرى. */
function unifiedNavDoors() {
    return array(
        'HOME'  => array('name' => 'لوحة الإدارة',        'icon' => 'fa fa-gauge-high', 'flat' => true),
        'DAILY' => array('name' => 'العمل اليومي',        'icon' => 'fa fa-briefcase'),
        'APPR'  => array('name' => 'المتابعة والموافقات', 'icon' => 'fa fa-clipboard-check'),
        'REC'   => array('name' => 'السجلات الرئيسية',    'icon' => 'fa fa-database'),
        'REP'   => array('name' => 'التقارير والتحليلات', 'icon' => 'fa fa-chart-pie'),
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
            ORDER BY FIELD(n.door,'HOME','DAILY','APPR','REC','REP','SET'), n.sort_order, n.id";
    $items = array();
    $res = mysqli_query($conn, $sql);
    if ($res) { while ($row = mysqli_fetch_assoc($res)) { $items[] = $row; } }
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

    $lastGroup = null;
    foreach ($items as $it) {
        $gname = isset($it['group_name']) && $it['group_name'] !== null ? $it['group_name'] : '';
        if ($gname !== $lastGroup) {
            if ($gname !== '') {
                echo '<li class="nav-subhead" aria-hidden="true"><span>'
                   . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . '</span></li>' . "\n";
            }
            $lastGroup = $gname;
        }
        printNavLinkItem(array(
            'code' => $it['route'],
            'name' => $it['label_ar'],
            'icon' => $it['icon'],
        ), $basePrefix, $badges);
    }

    echo '  </ul>' . "\n";
    echo '</li>' . "\n";
}

/**
 * تصيير القائمة الموحّدة كاملةً للدور — بديلُ الكتل الثلاث والروابط الثابتة
 * (عدا الرئيسية والمراسلات — ثابتتان بقرار المالك).
 * الشارات بمفتاح المسار (route) — تُجمع في insidebar من مصادرها القائمة.
 */
function renderUnifiedNavigationV2($conn, $roleId, $basePrefix = '../', $badges = array()) {
    $items = getUnifiedNavItems($conn, $roleId);
    if (empty($items)) { return false; }
    $byDoor = array();
    foreach ($items as $it) { $byDoor[$it['door']][] = $it; }
    foreach (unifiedNavDoors() as $doorKey => $meta) {
        if (empty($byDoor[$doorKey])) { continue; }
        if (!empty($meta['flat'])) {
            // بابٌ مسطّح: عناصره تُطبع مباشرةً بلا رأس طيٍّ (لوحة الإدارة أول ما يُرى)
            foreach ($byDoor[$doorKey] as $it) {
                printNavLinkItem(array('code' => $it['route'], 'name' => $it['label_ar'], 'icon' => $it['icon']), $basePrefix, $badges);
            }
            continue;
        }
        printUnifiedNavDoor($doorKey, $meta, $byDoor[$doorKey], $basePrefix, $badges);
    }
    return true;
}
