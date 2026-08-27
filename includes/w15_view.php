<?php
/**
 * includes/w15_view.php — أدواتُ عرضٍ لأسطحِ المرحلةِ الخامسةَ عشرة (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **إسقاطٌ لا مصدر** (‏قيدُ المالك §١): كلُّ دالّةٍ هنا **تقرأ ولا تكتب**.
 *   ⛔ **ولا `insert` ولا `update` ولا `delete` في هذا الملفِّ ولا فيما يستدعيه**
 *   — والحاجبُ يقرأ شيفرةَ هذه الأسطحِ نفسِها ويسقط على أوّلِ كتابة.
 *
 * ◆ **والقراءةُ بمرجعٍ حيٍّ من جدولِ مالكِه** عبرَ `ExecProjectionService`،
 *   وبوّابةُ العزلِ تحقن الكيانَ (`DEC-OPEN-03`) — ⛔ ولا استعلامَ خامٍّ في سطح.
 *
 * ◆ **والنطاقُ من محرّكٍ واحد**: `ScopeEngine::visibility()` تعطي ما يراه
 *   صاحبُ الحساب، والرئيسُ والنائبُ والموظّفُ يمرّون بالشيفرةِ نفسِها.
 *   ⛔ **ولا ثلاثةَ أنظمة.**
 *
 * ◆ **والرؤيةُ لا تساوي السلطة**: `w15_may()` تسأل `ScopeEngine::authority()`
 *   **ولا تُشتقُّ من ظهورِ الشاشة**. فزرُّ الفعلِ لا يُعرَض إلّا بسلطةٍ مسجَّلة،
 *   وسلطةٌ غيرُ مسجَّلةٍ تعني **لا زرّ** لا «افترضْ نعم».
 *
 * ◆ **ونقاءُ لغةِ الواجهة** (‏قيدُ المالك §٨): الرمزُ يبقى لاتينيًّا في السجلِّ
 *   ويُعرَض عربيًّا من القاموسِ المركزيّ — ⛔ ولا مصفوفةَ مسمّياتٍ مكتوبةٌ هنا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/w7_codes.php';
require_once __DIR__ . '/../app/Services/Exec/ScopeEngine.php';
require_once __DIR__ . '/../app/Services/Exec/ExecProjectionService.php';
require_once __DIR__ . '/../app/Services/Exec/ExecDecisionRouter.php';

use App\Services\Exec\ScopeEngine;
use App\Services\Exec\ExecProjectionService;

if (!function_exists('ems_w15_ar')) {

    /** مسمّى الرمزِ من القاموسِ المركزيّ — والرمزُ نفسُه إن غاب */
    function ems_w15_ar($code)
    {
        $c = isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null;
        return ems_w7_ar($code, $c);
    }
    function ems_w15_state($v) { return htmlspecialchars(ems_w15_ar($v)); }

    /** سياقُ المستخدمِ — الهويّةُ والكيانُ والدور */
    function w15_ctx()
    {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        return array(
            'role'       => $role,
            'is_super'   => ($role === '-1'),
            'company_id' => isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0,
            'user_id'    => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
            'id'         => isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0,
        );
    }

    /** صلاحيّةُ الشاشةِ — والسوبر أدمن لا يُستثنى في `get_module_permissions` */
    function w15_perms($conn, $code, $is_super)
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
    function w15_gate($is_super = false)
    {
        $gate = ems_tenant_db();
        return $is_super ? $gate->forAllTenants('w15 spaces super view') : $gate;
    }

    /** ما يراه صاحبُ الحساب — من محرّكِ النطاقِ الواحد */
    function w15_visibility($conn, array $ctx)
    {
        return ScopeEngine::visibility($conn, $ctx);
    }

    /**
     * **قراءةٌ حيّةٌ مُسقَطة** — الجدولُ لمالكِه والنطاقُ من المحرّك.
     * ⛔ ولا نسخةَ دوريّةً ولا مخزنَ محلّيّ.
     */
    function w15_rows($is_super, $table, array $vis, array $opt = array())
    {
        return ExecProjectionService::read(w15_gate($is_super), $table, $vis, $opt);
    }

    /**
     * **هل له سلطةُ هذا الفعل؟** — من سجلِّ السلطةِ لا من ظهورِ الشاشة.
     * وغيابُ القاعدةِ يعني **لا** — ⛔ ولا يُفترَض.
     */
    function w15_may($conn, array $ctx, $actionKey, array $extra = array())
    {
        $a = ScopeEngine::authority($conn, $ctx, $actionKey, $extra);
        return $a['verdict'] === ScopeEngine::OK;
    }

    /** حكمُ السلطةِ كاملًا حين يلزم رمزُ الردِّ لا نعم/لا */
    function w15_authority($conn, array $ctx, $actionKey, array $extra = array())
    {
        return ScopeEngine::authority($conn, $ctx, $actionKey, $extra);
    }

    /* ── عدّاداتٌ مشتقّةٌ من الصفوفِ المقروءةِ لا مخزَّنة ─────────────────── */

    function ems_w15_count(array $rows, $col, $val) { return ExecProjectionService::countBy($rows, $col, $val); }
    function ems_w15_distinct(array $rows, $col)    { return ExecProjectionService::distinct($rows, $col); }

    function ems_w15_empty(array $rows, $col)
    {
        $n = 0;
        foreach ($rows as $r) {
            if (!isset($r[$col]) || $r[$col] === null || (string) $r[$col] === '') { $n++; }
        }
        return $n;
    }
    function ems_w15_filled(array $rows, $col) { return count($rows) - ems_w15_empty($rows, $col); }

    function ems_w15_sumf(array $rows, $col)
    {
        $s = 0.0;
        foreach ($rows as $r) { $s += isset($r[$col]) ? (float) $r[$col] : 0; }
        return $s;
    }

    /** رقمٌ للعرضِ — قيمةُ بياناتٍ لا مسمّى واجهة */
    function ems_w15_num($v) { return number_format((float) $v, 2); }

    /** نصٌّ للعرضِ — والعدمُ شرطةٌ لا فراغٌ يلتبس بعمودٍ مفقود */
    function ems_w15_txt($v)
    {
        $v = (string) $v;
        return $v === '' ? '—' : htmlspecialchars($v);
    }
}
