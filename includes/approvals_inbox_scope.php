<?php
/**
 * includes/approvals_inbox_scope.php — نطاقُ صندوقِ الاعتمادِ الموحَّد
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ الذي أوجدَ هذا الملفَّ** — INJ-0587، ومعيارُ قبولِه في السجلِّ الجامع:
 *   «الرقمُ على بلاطةِ ﴿موافقاتي﴾ في `main/my_workspace.php` = عددُ الصفوفِ في
 *    `Portal/approvals_inbox.php` بالضبط، **لكلِّ دور**».
 *
 * وكان الرقمان يُحسبان في ملفَّين بنصَّين مختلفين، فتفرَّقا في خمسةِ مواضعَ —
 * قِيسَ منها **حيًّا** أنَّ السوبر يُدرَج له 7,342 صفَّ مرحلةٍ وتعدُّ بلاطتُه
 * 1,501 (فرقُ 5,841). فالعلاجُ ليس مطابقةَ رقمٍ برقمٍ بل **إزالةُ التعريفِ
 * الثاني**: هنا التعريفُ الواحدُ، والعادُّ والعارضُ كلاهما يقرأُه.
 *
 * ── الانحرافاتُ الخمسةُ وحكمُ كلٍّ ──────────────────────────────────────────
 * ① `approval_links`: كانت البلاطةُ تعدُّ الموجَّهَ **للمستخدمِ** وحدَه، والصندوقُ
 *    يُدرج الموجَّهَ **للدورِ** أيضًا (`approver_user_id IS NULL AND approver_role`).
 *    ◆ الحكمُ: يُؤخذ **الأوسعُ** — فالموجَّهُ لدورِك عملٌ عليك حقًّا. (كامنٌ اليوم:
 *      صفرُ رابطٍ موجَّهٍ بدورٍ، فالفرقُ لا يشتعل — ويشتعل أوّلَ ما يوجَّه واحدٌ.)
 * ② `requests`: الصندوقُ يضمُّ `JOIN request_types` **داخليًّا**، فطلبٌ بنوعٍ غيرِ
 *    مسجَّلٍ يُعدُّ ولا يُعرَض.
 *    ◆ الحكمُ: يُوحَّد على المعروضِ (بالضمِّ) **ولا يُخفى الفارق**: يُبلَّغ عددُ
 *      المتعذِّرِ عرضُه في `ems_approvals_inbox_counts()['untyped']` ليُرى لا ليُدفن.
 * ③ `unit_entries`: البلاطةُ كتبت شرطَ «ما قبلَ السلسلة» **بيدها**
 *    (`state='converted' AND converted_at IS NULL`) وهو نصُّ `ems_uc_prechain_sql()`
 *    مكرَّرًا — فأيُّ تعديلٍ في المركزيةِ يُخلِّف البلاطةَ صامتةً.
 *    ◆ الحكمُ: تُستدعى المركزيةُ، ولا يُكتب الشرطُ مرتين.
 * ④ السوبر أدمن: الصندوقُ يفتح له **كلَّ** المراحلِ (`$is_super_admin || …`)
 *    والبلاطةُ تُطابق دورَه وحدَه.
 *    ◆ الحكمُ: خريطةُ المراحلِ هنا تعرف السوبرَ — فالقارئان سواء.
 * ⑤ `LIMIT 60`: الصندوقُ يعرض ستِّين ويعدُّ العادُّ الكلَّ، فرقمٌ فوق الستِّين
 *    لا يطابق سطورًا معروضةً أبدًا.
 *    ◆ الحكمُ: **لا يُكذَبُ بالسقفِ ولا يُنقَصُ الرقمُ الصادق.** يُرجَع الاثنان:
 *      `total` (الحقيقةُ الكاملةُ — وهي ما تعرضه البلاطة) و`shown` (المقصوصُ
 *      على السقف)، ليُعلن العارضُ «يُعرَض ﻥ من ﻡ» فيتصالح الرقمان بالإفصاح.
 *
 * ◆ ولا شرطَ هنا يُبنى بلصقِ مُدخلٍ: المعرِّفاتُ تُقسَر أعدادًا، والدورُ يُهرَّب.
 * ═══════════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/unit_chain_helpers.php';

/** سقفُ ما يعرضه الصندوقُ لكلِّ مكوِّن — مُعلَنٌ **مرةً واحدةً** لا ثلاثًا. */
function ems_approvals_inbox_cap()
{
    return 60;
}

/** حالاتُ الطلبِ التي تعني «ينتظر فعلًا من حاملِه». */
function ems_approvals_request_states()
{
    return array('submitted', 'routed', 'in_approval', 'approved');
}

/** خريطةُ مرحلةِ الوحدةِ ⇒ الأدوارُ التي تعتمدها. */
function ems_approvals_stage_roles()
{
    return array(
        'submitted'        => array('5', '6'),    // التشغيل — اعتماد الموقع
        'site_approved'    => array('1'),         // الإدارة — مطابقة التشغيل
        'parties_review'   => array('2', '4'),    // المراجعة — الأطراف
        'parties_approved' => array('12'),        // المبيعات/العقود
        'sales_approved'   => array('17', '19'),  // المالية — التحويل
    );
}

/**
 * المراحلُ التي يعتمدها هذا الدور. **والسوبرُ يعتمد الكلَّ** — وهذا سبب
 * الانحرافِ ④ الذي قِيس حيًّا؛ فمعرفتُه هنا تكفي القارئين معًا.
 */
function ems_approvals_my_stages($role, $isSuper)
{
    $out = array();
    foreach (ems_approvals_stage_roles() as $stage => $roles) {
        if ($isSuper || in_array((string) $role, $roles, true)) { $out[] = $stage; }
    }
    return $out;
}

/**
 * شروطُ `WHERE` الثلاثةُ — **يُعَدُّ بها ويُعرَضُ بها سواءً**.
 * تُرجع مصفوفةً: requests · approval_links · unit_entries (أو null إن لم يكن
 * للدورِ مرحلةٌ)، وكلٌّ جاهزةٌ للصقِ بعد `WHERE`.
 */
function ems_approvals_inbox_where(mysqli $conn, $co, $uid, $role, $isSuper)
{
    $co = (int) $co;
    $uid = (int) $uid;
    $roleEsc = "'" . mysqli_real_escape_string($conn, (string) $role) . "'";
    $st = "'" . implode("','", ems_approvals_request_states()) . "'";

    $stages = ems_approvals_my_stages($role, $isSuper);
    $ue = null;
    if ($stages) {
        $in = "'" . implode("','", $stages) . "'";
        /* ③ الشرطُ من المركزيةِ لا بيدٍ ثانية */
        $ue = "ue.company_id = {$co} AND ue.state IN ({$in})
               AND NOT " . ems_uc_prechain_sql('ue');
    }

    return array(
        /* ② بالضمِّ الداخليِّ — بذاتِ ما يعرضه الصندوق */
        'requests' => "rq.company_id = {$co} AND rq.current_holder_user_id = {$uid}
                       AND rq.status IN ({$st})",
        /* ① الأوسعُ: الموجَّهُ لك أو لدورِك */
        'approval_links' => "al.company_id = {$co} AND al.status = 'pending'
                             AND (al.approver_user_id = {$uid}
                                  OR (al.approver_user_id IS NULL AND al.approver_role = {$roleEsc}))",
        'unit_entries' => $ue,
    );
}

/**
 * العددُ — بذاتِ شروطِ العرض.
 * يُرجع: total (المجموعُ الصادقُ · وهو رقمُ البلاطة) · shown (المقصوصُ على
 * السقفِ · وهو ما يُعرَض) · parts (تفصيلُ كلِّ مكوِّن) · untyped (طلباتٌ على
 * حاملِها فعلٌ ونوعُها غيرُ مسجَّلٍ فلا تُعرَض — تُعلَن ولا تُدفَن).
 */
function ems_approvals_inbox_counts(mysqli $conn, $co, $uid, $role, $isSuper)
{
    $w = ems_approvals_inbox_where($conn, $co, $uid, $role, $isSuper);
    $cap = ems_approvals_inbox_cap();
    $one = function ($sql) use ($conn) {
        $r = mysqli_query($conn, $sql);
        if (!$r) { return 0; }
        $x = mysqli_fetch_assoc($r);
        return $x ? (int) reset($x) : 0;
    };

    $parts = array();
    $parts['requests'] = $one("SELECT COUNT(*) n FROM requests rq
                                 JOIN request_types rt ON rt.code = rq.request_type_code
                                WHERE " . $w['requests']);
    $parts['approval_links'] = $one("SELECT COUNT(*) n FROM approval_links al
                                      WHERE " . $w['approval_links']);
    $parts['unit_entries'] = $w['unit_entries'] === null ? 0
        : $one("SELECT COUNT(*) n FROM unit_entries ue WHERE " . $w['unit_entries']);

    /* ② الفارقُ يُعلَن: على حاملِها فعلٌ ولا نوعَ مسجَّلًا فلا سطرَ يُعرَض */
    $untyped = $one("SELECT COUNT(*) n FROM requests rq
                      WHERE " . $w['requests'] . "
                        AND NOT EXISTS (SELECT 1 FROM request_types rt
                                         WHERE rt.code = rq.request_type_code)");

    $total = 0; $shown = 0;
    foreach ($parts as $n) { $total += $n; $shown += min($n, $cap); }

    return array('total' => $total, 'shown' => $shown, 'cap' => $cap,
                 'parts' => $parts, 'untyped' => $untyped);
}
