<?php
/**
 * includes/unit_chain_helpers.php — عدّة سلسلة اعتماد الوحدات (E-02 · DEC-01 ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * التعريف المركزي الواحد لِـ:
 *   ① «الموروث قبل السلسلة»: صف state='converted' بلا converted_at — رُدم
 *      تاريخيًّا قبل تفعيل المحرّك (قرار المالك 2026-08-05: يُوسَم ويُستثنى
 *      من القياس ولا يُعاد عبر السلسلة ولا يُعدّ معتمدًا ضمنًا).
 *   ② مقاييس التأخر لمؤشر DEC-01 ⑦ (الوحدات غير المعتمدة · أقدمها بالأيام ·
 *      نسبة المعتمد إلى المسجَّل أسبوعيًّا — المستهدف ≥95٪ وصفر فوق 7 أيام).
 * كل قارئ (اللوحة · الكرونان · الحزام) يمرّ من هنا — فلا يتفرق التعريف.
 */

/** شرط SQL للموروث قبل السلسلة على الاسم المستعار المعطى. */
function ems_uc_prechain_sql($alias = 'ue')
{
    return "($alias.state = 'converted' AND $alias.converted_at IS NULL)";
}

/** الحالات الوسيطة: دخلت السلسلة ولم تبلغ نهايتها ولم تخرج منها. */
function ems_uc_pending_states()
{
    return array('submitted', 'site_approved', 'parties_review', 'parties_approved', 'sales_approved');
}

/**
 * ⇐ INJ-0334 · الحالاتُ التي **تُعدُّ ساعاتُها واقعةً مقبولة**: ما جاوز اعتمادَ
 * الموقعِ فصاعدًا. وقبلَ اليومِ كانت شاشةُ الوقائيةِ تسأل عن `'approved'` —
 * **وهي ليست في تعدادِ العمود أصلًا**، فبقي المتراكمُ يُحسب من `converted` وحدَه
 * وسقطت ١٥٠٠ ساعةٍ معتمدةٍ من عدّادِ الغيار صامتةً.
 * ◆ والتعريفُ هنا **واحدٌ** تنادِيه الوقائيةُ ولوحةُ الإنجاز — فلا يتفرّق رقمان.
 */
function ems_uc_accepted_states()
{
    return array('site_approved', 'parties_approved', 'sales_approved', 'converted');
}

/** شرطُ القبولِ جاهزًا على اسمٍ مستعار: `ue.state IN ('…')`. */
function ems_uc_accepted_sql($alias = 'ue')
{
    return $alias . ".state IN ('" . implode("','", ems_uc_accepted_states()) . "')";
}

/**
 * مقاييس DEC-01 ⑦ لشركة: العالق وعمر أقدمه، والمسجَّل/المكتمل في آخر 7 أيام.
 * «المكتمل» = بلغ converted عبر المحرّك (converted_at مملوء) — فالموروث لا يعدّ.
 */
function ems_uc_lag_metrics(mysqli $conn, $companyId)
{
    $companyId = (int) $companyId;
    $pend = "'" . implode("','", ems_uc_pending_states()) . "'";
    $m = array('pending' => 0, 'oldest_days' => 0, 'week_registered' => 0, 'week_converted' => 0, 'ratio' => null);

    $r = mysqli_query($conn,
        "SELECT COUNT(*) n, COALESCE(MAX(DATEDIFF(CURDATE(), entry_date)),0) oldest
           FROM unit_entries ue
          WHERE ue.company_id = {$companyId} AND ue.state IN ($pend)
            AND NOT " . ems_uc_prechain_sql('ue'));
    if ($r && ($x = mysqli_fetch_assoc($r))) {
        $m['pending'] = (int) $x['n'];
        $m['oldest_days'] = (int) $x['oldest'];
    }

    $r = mysqli_query($conn,
        "SELECT
            SUM(ue.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) wk_reg,
            SUM(ue.converted_at IS NOT NULL AND ue.converted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) wk_conv
           FROM unit_entries ue
          WHERE ue.company_id = {$companyId} AND NOT " . ems_uc_prechain_sql('ue'));
    if ($r && ($x = mysqli_fetch_assoc($r))) {
        $m['week_registered'] = (int) $x['wk_reg'];
        $m['week_converted']  = (int) $x['wk_conv'];
        $m['ratio'] = $m['week_registered'] > 0
            ? round(100.0 * $m['week_converted'] / $m['week_registered'], 1) : null;
    }
    return $m;
}

/**
 * مهلة الحلقة القادمة بالساعات لكل حالة وسيطة — v1 ثوابت من مدد approval_chains
 * الحية (24/48/72)؛ ربطها صفًّا بسياسة السلسلة لاحقٌ مع المهمة ①.
 */
function ems_uc_stage_sla_hours($state)
{
    $map = array(
        'submitted'        => 24,  // بانتظار اعتماد الموقع
        'site_approved'    => 48,  // بانتظار الأطراف
        'parties_review'   => 48,
        'parties_approved' => 48,  // بانتظار العقود/المبيعات
        'sales_approved'   => 72,  // بانتظار التحويل المالي
    );
    return isset($map[$state]) ? $map[$state] : 48;
}

/** أدوار لوحةِ من يملك معالجة الحالة — لتوجيه إشعار التصعيد الساعي. */
function ems_uc_stage_owner_roles($state)
{
    $map = array(
        'submitted'        => array(6),       // مدير الموقع/الحركة
        'site_approved'    => array(1),       // إدارة التشغيل
        'parties_review'   => array(2, 27),   // الموردون والقوى
        'parties_approved' => array(12),      // المبيعات والعقود
        'sales_approved'   => array(17, 19),  // المالية
    );
    return isset($map[$state]) ? $map[$state] : array(1);
}

/** إشعار بعطالة يومية: لا يُكرر لنفس المستلم ونفس الرابط في اليوم نفسه. */
function ems_uc_notify_once(mysqli $conn, $companyId, $userId, $title, $link)
{
    $companyId = (int) $companyId;
    $userId = (int) $userId;
    $t = mysqli_real_escape_string($conn, mb_substr($title, 0, 190));
    $l = mysqli_real_escape_string($conn, mb_substr($link, 0, 190));
    $r = mysqli_query($conn,
        "SELECT id FROM fin_notifications
          WHERE company_id={$companyId} AND target_user_id={$userId}
            AND link='{$l}' AND created_at >= CURDATE() LIMIT 1");
    if ($r && mysqli_num_rows($r)) { return false; }
    mysqli_query($conn,
        "INSERT INTO fin_notifications (company_id, target_level, target_user_id, title, link, is_read, created_at)
         VALUES ({$companyId}, 'all', {$userId}, '{$t}', '{$l}', 0, NOW())");
    return true;
}
