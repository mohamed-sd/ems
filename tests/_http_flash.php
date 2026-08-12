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
    /**
     * الرسالةُ من مُعامَلِ العنوان — لشاشاتٍ تضعها هناك فعلًا.
     *
     * **و`+` يُفكُّ إلى فراغ.** الشاشاتُ تبني المُعامَلَ بـ`urlencode` فيصير الفراغُ
     * `+`، فقُرئت رسالةٌ صحيحةٌ هكذا: «سُجّل+السعر+✅+—+وقُيّم+2+حدثًا» ولم تطابق
     * إبرةَ الفاحصِ ذاتَ الفراغات. فيُستخرَج المُعامَلُ خامًا ويُفَكُّ بـ`urldecode`
     * (تفكُّ `%xx` **و`+`** معًا) — لا بـ`rawurldecode` التي تُبقي `+` حرفًا.
     */
    function ems_flash_url_msg($headers)
    {
        $to = ems_flash_location($headers);
        if ($to === '') { return ''; }
        $q = (string) parse_url($to, PHP_URL_QUERY);
        if ($q === '') { return ''; }
        if (preg_match('~(?:^|&)msg=([^&]*)~', $q, $m)) { return urldecode($m[1]); }
        return '';
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

        /* ══ **`strip_tags` تمحو جسمَ `<script>` كلَّه — وفيه الوميضُ.**
             هذا هو **الجذرُ المقيسُ** لفشلِ كلِّ قراءاتِ الوميضِ في المسابير:
             الصفحةُ تُجلَب كاملةً (**61,442 بايتًا** مقيسة) والرسالةُ فيها بنصِّها
             العربيِّ الصريح، ثم يُرجِع `strip_tags` **58 حرفًا فقط** — لأن
             `inheader.php:145` يبثُّ الوميضَ **داخلَ `<script>`** حمولةً JSON
             (`window.EmsAlert.show({text:…})`)، وPHP تُسقط `<script>` **بمحتواه**.
             فيُقرأ «لا رسالة» والرسالةُ حاضرةٌ في البايتات.
           ⇒ تُقرأ الرسالةُ من **الجسمِ الخام** بمصدرَيها المعلَنَين في
             `inheader.php`: حمولةُ `"text":"…"` أوّلًا، ثم لافتةُ `<noscript>`
             الاحتياطية، ثم نصُّ الصفحةِ المنقَّى لِما ليس وميضًا. */
        $parts = array();
        if (preg_match_all('~"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"~', $body, $fm)) {
            foreach ($fm[1] as $t) {
                $parts[] = str_replace(array('\\/', '\\"', '\\\\'), array('/', '"', '\\'), $t);
            }
        }
        if (preg_match_all('~<noscript>(.*?)</noscript>~is', $body, $nm)) {
            foreach ($nm[1] as $blk) { $parts[] = html_entity_decode(strip_tags($blk), ENT_QUOTES, 'UTF-8'); }
        }
        $plain = strip_tags($body);
        $parts[] = $plain;

        $joined = implode(' · ', $parts);
        /* وضغطُ الفراغاتِ بـ`u` يُرجِع `NULL` على بايتٍ غيرِ صالح — فيُجَسُّ المُرجَع. */
        $out = preg_replace('~\s+~u', ' ', $joined);
        if ($out === null) { $out = preg_replace('~\s+~', ' ', $joined); }
        if ($out === null) { $out = $joined; }
        return (string) $out;
    }
}

if (!function_exists('ems_flash_text_following')) {
    /**
     * نصُّ الوميضِ حين يكون التوجيهُ **أكثرَ من خَطوة**.
     *
     * ردُّ الحوكمةِ لا يعود دائمًا إلى الشاشةِ نفسِها: حارسُ الكتابةِ المركزيُّ
     * يرمي إلى `../main/dashboard.php`، **والداشبورد يوجّه بدورِه** إلى لوحةِ
     * الدورِ (مقيسٌ: القارئُ المالي ينتهي إلى `Finance/cfo_daily_board_fin.php`).
     * فمن يتبع خَطوةً واحدةً يقرأ صفحةَ توجيهٍ خاويةً ويحكم «لا رسالة» — والرسالةُ
     * تنتظره عند نهايةِ السلسلة. فتُتبَع السلسلةُ كلُّها بجرَّةِ الجلسةِ نفسِها.
     *
     * @param string $headers ترويسةُ الاستجابةِ الحاملةِ Location
     * @param string $dirBase أصلُ المسارِ النسبيّ
     * @param string $jarFile جرَّةُ الجلسة — بها وحدَها يُقرأ الوميض
     */
    function ems_flash_text_following($headers, $dirBase, $jarFile)
    {
        $to = ems_flash_location($headers);
        if ($to === '') { return ''; }
        if (strpos($to, 'http') !== 0) {
            $to = rtrim((string) $dirBase, '/') . '/' . ltrim($to, '/');
        }
        $ch = curl_init($to);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false,
            CURLOPT_COOKIEJAR => $jarFile, CURLOPT_COOKIEFILE => $jarFile,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 8, CURLOPT_TIMEOUT => 60,
        ));
        $body = (string) curl_exec($ch);
        curl_close($ch);
        if ($body === '') { return ''; }

        /* الجسمُ الخامُ — لأن `strip_tags` تمحو جسمَ `<script>` وفيه الوميض */
        $parts = array();
        if (preg_match_all('~"text"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"~', $body, $fm)) {
            foreach ($fm[1] as $t) {
                $parts[] = str_replace(array('\\/', '\\"', '\\\\'), array('/', '"', '\\'), $t);
            }
        }
        if (preg_match_all('~"code"\s*:\s*"([^"]*)"~', $body, $cm)) {
            foreach ($cm[1] as $t) { $parts[] = $t; }
        }
        if (preg_match_all('~<noscript>(.*?)</noscript>~is', $body, $nm)) {
            foreach ($nm[1] as $blk) { $parts[] = html_entity_decode(strip_tags($blk), ENT_QUOTES, 'UTF-8'); }
        }
        $joined = implode(' · ', $parts);
        $out = preg_replace('~\s+~u', ' ', $joined);
        if ($out === null) { $out = preg_replace('~\s+~', ' ', $joined); }
        return (string) ($out === null ? $joined : $out);
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

/* ═══════════════════════════════════════════════════════════════════════════
   الرمزُ المركزيُّ لجلسةِ مسبارٍ — يُقرأ من الصفحةِ مرةً ويُخزَّن لجرَّتِها
   ───────────────────────────────────────────────────────────────────────────
   الحارسُ المركزيُّ (`includes/security.php:404`) يقبل `$_POST['csrf_token']`
   **أو** ترويسةَ `X-CSRF-Token`. والمسابيرُ تبني حقولَ الطلبِ **يدويًّا** فلا
   تحمل أيَّهما — فتُردُّ 403 ويُقرأ صمتُها فشلًا في المنتج. والرمزُ **واحدٌ لكلِّ
   جلسة**، فيُقرأ مرةً لكلِّ جرَّةٍ ويُعاد استعمالُه.
   ═══════════════════════════════════════════════════════════════════════════ */
if (!function_exists('ems_http_csrf_token')) {
    function ems_http_csrf_token($url, $jarFile)
    {
        static $cache = array();
        $k = (string) $jarFile;
        if (isset($cache[$k]) && $cache[$k] !== '') { return $cache[$k]; }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false,
            CURLOPT_COOKIEJAR => $jarFile, CURLOPT_COOKIEFILE => $jarFile,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 40,
        ));
        $body = (string) curl_exec($ch);
        curl_close($ch);

        $tok = '';
        if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $body, $m)) { $tok = $m[1]; }
        elseif (preg_match('~<meta\s+name="csrf-token"\s+content="([^"]+)"~i', $body, $m)) { $tok = $m[1]; }
        $cache[$k] = $tok;
        return $tok;
    }
}

if (!function_exists('ems_http_with_csrf')) {
    /** يُضيف الرمزَ المركزيَّ إلى حقولِ طلبٍ لا تحمله — وإلا تُركت كما هي. */
    function ems_http_with_csrf($post, $url, $jarFile)
    {
        if (!is_array($post)) { return $post; }
        if (isset($post['csrf_token']) && $post['csrf_token'] !== '') { return $post; }
        $t = ems_http_csrf_token($url, $jarFile);
        if ($t !== '') { $post['csrf_token'] = $t; }
        return $post;
    }
}
