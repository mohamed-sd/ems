<?php
/**
 * tests/_http_flash.php — قراءةُ رسالةِ الشاشةِ من **وميضِ الجلسةِ** لا من العنوان
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المشترَكُ الذي يعالجه هذا الملف** (مقيسٌ في 11 مسبارَ HTTP):
 *
 * `ems_gov_flash_redirect` (`includes/permissions_helper.php:139`) يخزّن نصَّ
 * الرسالةِ في `$_SESSION['ems_flash_gov']` ثم يوجّه **بعنوانٍ مجرَّدٍ بلا مُعامَلات**.
 * فكلُّ مسبارٍ يقرأ `?msg=` من ترويسةِ `Location` يجد **فراغًا دائمًا** — لا لأن
 * الفعلَ أخفق بل لأنه يفتّش في الحقلِ الخطأ. فيُقرأ صمتُ المُعامَلِ فشلًا في
 * المنتج، والمنتجُ نفَّذ الفعلَ وكتب رسالتَه حيث ينبغي.
 *
 * وليست كلُّ الشاشاتِ كذلك: `Procurement/*` و`Finance/periods_fin.php`
 * و`FinRequests/request_actions.php` و`Suppliers/settlements.php` تضع الرسالةَ
 * **في العنوان** فعلًا — فالقراءةُ منه صحيحةٌ هناك. ولذلك لا يُبدَّل المقياسُ
 * جملةً: تُستعمل `ems_flash_or_msg()` التي تقرأ **الاثنين** وتُرجِع ما وُجد،
 * فيصحُّ الحكمُ على الشاشتين بلا استثناءٍ مكتوبٍ في الفاحص.
 *
 * الاستعمال في المسبار:
 *     require_once __DIR__ . '/_http_flash.php';
 *     $txt = ems_flash_or_msg($h, $BASE . '/Contracts', function ($u) use ($jar) {
 *         list($c, $hh, $b) = cs_req($u, $jar);   // دالةُ الطلبِ في المسبارِ نفسِه
 *         return $b;
 *     });
 *     check(mb_strpos($txt, 'أُنشئ') !== false, '…');
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (!function_exists('ems_flash_location')) {
    /** ترويسةُ `Location` كما هي (أو '' إن لم تكن). */
    function ems_flash_location($headers)
    {
        return preg_match('~Location:\s*(\S+)~i', (string) $headers, $m) ? trim($m[1]) : '';
    }
}

if (!function_exists('ems_flash_url_msg')) {
    /** الرسالةُ من مُعامَلِ العنوان — لشاشاتٍ تضعها هناك فعلًا. */
    function ems_flash_url_msg($headers)
    {
        $to = ems_flash_location($headers);
        if ($to === '') { return ''; }
        $q = (string) parse_url($to, PHP_URL_QUERY);
        if ($q === '') { return ''; }
        $p = array();
        parse_str($q, $p);
        return isset($p['msg']) ? (string) $p['msg'] : '';
    }
}

if (!function_exists('ems_flash_page_text')) {
    /**
     * يتبع التوجيهَ ويُرجِع **نصَّ الصفحةِ التالية** منقًّى من الوسوم — وفيه
     * وميضُ الجلسةِ مُصيَّرًا كما يراه المستخدم.
     *
     * @param string   $headers ترويسةُ الاستجابةِ التي تحمل Location
     * @param string   $dirBase أصلُ المسارِ النسبيِّ (مثل http://localhost/ems/Contracts)
     * @param callable $get     دالةُ طلبٍ تُرجِع جسمَ الصفحةِ لعنوانٍ كامل
     */
    function ems_flash_page_text($headers, $dirBase, callable $get)
    {
        $to = ems_flash_location($headers);
        if ($to === '') { return ''; }
        if (strpos($to, 'http') !== 0) {
            $to = rtrim((string) $dirBase, '/') . '/' . ltrim($to, '/');
        }
        $body = (string) call_user_func($get, $to);
        if ($body === '') { return ''; }
        return (string) preg_replace('~\s+~u', ' ', strip_tags($body));
    }
}

if (!function_exists('ems_flash_or_msg')) {
    /**
     * **المقياسُ الموحَّد**: يقرأ مُعامَلَ العنوانِ أوّلًا (فهو الأرخص وأدقُّ حين
     * يوجد)، وإن كان فارغًا اتّبع التوجيهَ وقرأ وميضَ الجلسةِ من الصفحة.
     * فيصحُّ على الشاشتين: التي تضع الرسالةَ في العنوان والتي تضعها في الجلسة.
     */
    function ems_flash_or_msg($headers, $dirBase, callable $get)
    {
        $m = ems_flash_url_msg($headers);
        if (trim($m) !== '') { return $m; }
        return ems_flash_page_text($headers, $dirBase, $get);
    }
}
