<?php
/**
 * Financing/w12_view.php — أدواتُ عرضٍ لأسطحِ المرحلةِ الثانيةَ عشرة (RPR-W12)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الرمزُ يبقى لاتينيًّا في السجلِّ ويُعرَض عربيًّا من القاموس** — القيمةُ
 *   تُقارَن في الشيفرةِ وفي `CHECK`، ونزعُ لاتينيّتِها يفكُّ المقارنةَ بلا خطإٍ
 *   يظهر (‏W06 · «نصٌّ يُقارَن لا يُعرَض»). فالعرضُ عبرَ `repair01_w6_code_dict`
 *   وحدَه، و⛔ **لا مصفوفةَ مسمّياتٍ عربيّةٍ مكتوبةٌ هنا**.
 *
 * ◆ **والعدّاداتُ مشتقّةٌ من الصفوفِ المقروءةِ لا مخزَّنة** — فالبطاقةُ تعرض ما
 *   في الجدولِ نفسِه، ورقمانِ من مصدرَين يتفرّقان.
 *
 * ◆ **وبوّابةُ العزلِ تحقن الكيانَ** (‏`DEC-OPEN-03`): لا صفَّ من كيانٍ آخرَ يظهر.
 */
require_once __DIR__ . '/../includes/w7_codes.php';

if (!function_exists('ems_w12_ar')) {
    /** مسمّى الرمزِ من القاموسِ المركزيّ — والرمزُ نفسُه إن غاب */
    function ems_w12_ar($code)
    {
        $c = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
        return ems_w7_ar($code, $c);
    }
    function ems_w12_state($v) { return htmlspecialchars(ems_w12_ar($v)); }

    /** سياقُ المستخدمِ — الدورُ والكيانُ والهويّة */
    function w12_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'       => $role,
            'is_super'   => ($role === '-1'),
            'company_id' => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'    => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }

    /** صلاحيّةُ الشاشةِ — والسوبر أدمن لا يُستثنى في `get_module_permissions` */
    function w12_perms($conn, $code, $is_super)
    {
        $p = check_page_permissions($conn, $code);
        return array(
            'can_view'   => $is_super ? true : $p['can_view'],
            'can_add'    => $is_super ? true : $p['can_add'],
            'can_edit'   => $is_super ? true : $p['can_edit'],
            'can_delete' => $is_super ? true : $p['can_delete'],
        );
    }

    /** بوّابةُ العزل — والرؤيةُ العابرةُ محروسةٌ لا مفتوحة */
    function w12_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('w12 financing super view') : $gate;
    }

    /** قراءةٌ آمنةٌ لصفوفِ جدولٍ عبرَ البوّابة — والخطأُ يُسجَّل ولا يُبتلَع صامتًا */
    function w12_rows($is_super, $table, array $opt = array())
    {
        try { return w12_gate($is_super)->select($table, $opt); }
        catch (\Throwable $t) { error_log('w12_rows ' . $table . ': ' . $t->getMessage()); return array(); }
    }

    /** عدُّ الصفوفِ التي يساوي عمودُها قيمةً — بالمقارنةِ النصّيّةِ لا بالنوع */
    function ems_w12_count(array $rows, $col, $val)
    {
        $n = 0;
        foreach ($rows as $r) {
            if (isset($r[$col]) && (string) $r[$col] === (string) $val) { $n++; }
        }
        return $n;
    }

    /** عددُ القيمِ المتمايزةِ في عمود */
    function ems_w12_distinct(array $rows, $col)
    {
        $seen = array();
        foreach ($rows as $r) {
            if (isset($r[$col]) && (string) $r[$col] !== '') { $seen[(string) $r[$col]] = true; }
        }
        return count($seen);
    }

    /** مجموعُ عمودٍ عشريّ */
    function ems_w12_sumf(array $rows, $col)
    {
        $s = 0.0;
        foreach ($rows as $r) { $s += isset($r[$col]) ? (float) $r[$col] : 0; }
        return $s;
    }

    /** مجموعُ عمودٍ صحيح */
    function ems_w12_sum(array $rows, $col)
    {
        $s = 0;
        foreach ($rows as $r) { $s += isset($r[$col]) ? (int) $r[$col] : 0; }
        return $s;
    }

    /** صفوفٌ عمودُها غيرُ صفريّ */
    function ems_w12_nonzero(array $rows, $col)
    {
        $n = 0;
        foreach ($rows as $r) { if (abs((float) (isset($r[$col]) ? $r[$col] : 0)) > 0.005) { $n++; } }
        return $n;
    }

    /** رقمٌ للعرضِ بفاصلةِ الآلافِ ومنزلتَين — قيمةُ بياناتٍ لا مسمّى واجهة */
    function ems_w12_num($v) { return number_format((float) $v, 2); }
}
