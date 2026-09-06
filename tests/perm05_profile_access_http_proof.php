<?php
/**
 * tests/perm05_profile_access_http_proof.php — برهانٌ حيٌّ: «الملفُّ الشخصيُّ» يُفتح لكلِّ دور
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا برهانٌ حيٌّ لا قراءةُ مخزن**: العطبُ المُبلَّغُ («لا تملك صلاحية عرض
 *   هذه الصفحة» في كلِّ الأدوار) قرارٌ يُتَّخذ في مسارِ الطلبِ لا في جدول.
 *   وبندٌ مكتوبٌ في `gov_profile_items` **ليس برهانَ فتحٍ**: بينه وبين الشاشةِ
 *   حلُّ الوحدةِ بالمسار · طبقةُ القوالب · حارسُ الفعل · بوابةُ المستأجر.
 *   فيُقاس **ما يردُّه الخادمُ** لجلسةٍ حقيقيّةٍ بعدَ دخولٍ حقيقيّ.
 *
 * ◆ **والحجبُ هنا تحويلةٌ لا 403**: `ems_gov_flash_redirect` تردُّ 302 إلى
 *   اللوحةِ ورسالةٌ في الومضة. فقراءةُ «200» وحدَها لا تكفي — يُقاس أيضًا
 *   ألّا يكون الردُّ تحويلةً، وأن يحمل الجسدُ بطاقةَ الشاشةِ نفسِها.
 *
 * ◆ **وما لا يقيسه مُعلَنٌ**: يقيس الحساباتِ المذكورةَ في `tools/uxw_accounts.txt`
 *   لا الأدوارَ الخمسةَ والثلاثين كلَّها (ليس لكلِّ دورٍ حسابُ اختبارٍ بكلمةِ
 *   المرورِ الموحَّدة)، ولا يقيس المستخدمَ #1 (شركةٌ معلَّقةٌ · بلا تغطيةِ قالبٍ
 *   نافذٍ · حكمٌ مسجَّلٌ سابقٌ لا شأنَ لهذه الجولةِ به).
 *
 * ◆ يتطلّب Apache حيًّا على http://localhost/ems
 *   php tests/perm05_profile_access_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$BASE   = 'http://localhost/ems';
$SCREEN = 'main/profile.php';
$PASSWD = '12345678';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m, $d = '') { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✖ {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"); }

function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $cd  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', ''); }
    return array($cd, substr($raw, 0, $hs), substr($raw, $hs));
}

/** رمزُ CSRF يُقرأ أولًا — بدونه يُردُّ الطلبُ فتُقرأ الجلسةُ الغائبةُ حجبًا. */
function login($base, $user, $pw, $jar)
{
    if (file_exists($jar)) { @unlink($jar); }
    list(, , $b) = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($base . '/login.php', $jar, array(
        'username' => $user, 'password' => $pw,
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
}

echo "═══ برهانٌ حيٌّ · «الملفُّ الشخصيُّ» عبرَ الأدوار ═══\n\n";

list($c0) = req($BASE . '/login.php', sys_get_temp_dir() . '/p05_ping.jar');
if ($c0 === 0) { exit("✖ Apache لا يستجيب على {$BASE} — البرهانُ الحيُّ متعذِّر\n"); }

/* الحساباتُ من دليلِ UAT (`tools/uxw_accounts.txt`) — مُنتقاةٌ لتغطيةِ إداراتٍ
   مختلفةٍ لا لتكرارِ إدارةٍ واحدة. */
$ACCOUNTS = array(
    'محمد'            => 'إدارة التشغيل',
    'مبيعات'          => 'المبيعات',
    'صيانة'           => 'الصيانة',
    'مصعب'            => 'الموردين',
    'تنفيذ'           => 'الإدارة التنفيذية',
    'مشرف الصلاحيات'  => 'إدارة الصلاحيات',
    'مشرف المالية'    => 'المالية',
    'تمويل'           => 'التمويل',
    'مخاطر'           => 'المخاطر',
    'مراجع'           => 'المراجعة',
    'بلاغات'          => 'البلاغات',
    'موقع'            => 'إدارة الموقع',
    'مدير القوى'      => 'القوى التشغيلية',
);

$measured = 0; $skipped = array();
foreach ($ACCOUNTS as $user => $label) {
    $jar = sys_get_temp_dir() . '/p05_' . md5($user) . '.jar';
    list($lc) = login($BASE, $user, $PASSWD, $jar);
    if ($lc !== 200 && $lc !== 302) { $skipped[] = "{$user} (دخول HTTP {$lc})"; continue; }

    list($code, $hdr, $body) = req($BASE . '/' . $SCREEN, $jar);
    /* ما يزال على شاشةِ الدخول ⇒ لم تُنشأ جلسةٌ: يُعلَن تخطّيًا لا رسوبًا،
       فحسابٌ لا يوجد ليس حكمًا على الصلاحية. */
    if ($code === 302 && stripos($hdr, 'login.php') !== false) {
        $skipped[] = "{$user} (لا جلسة)"; continue;
    }
    $measured++;

    $redirected = ($code >= 300 && $code < 400);
    $denied     = (strpos($body, 'لا تملك صلاحية') !== false);
    $rendered   = (strpos($body, 'ems-profile') !== false || strpos($body, 'الملف الشخصي') !== false);

    if ($code === 200 && !$redirected && !$denied && $rendered) {
        ok(sprintf('%-16s (%s) — 200 · الشاشةُ مُصيَّرة', $user, $label));
    } else {
        bad(sprintf('%-16s (%s)', $user, $label),
            sprintf('HTTP %d%s%s%s', $code,
                $redirected ? ' · تحويلة' : '',
                $denied ? ' · رسالةُ حجب' : '',
                $rendered ? '' : ' · بلا بطاقةٍ في الجسد'));
    }
}

echo "\n─────────────────────────────────────────\n";
printf("حساباتٌ مقيسة: %d · نجح %d · رسب %d\n", $measured, $PASS, $FAIL);
if ($skipped) { printf("تُخطِّي (مُعلَنٌ لا مسكوتٌ عنه): %d ⇒ %s\n", count($skipped), implode(' · ', $skipped)); }
if ($measured === 0) { exit("✖ صفرٌ مقيسٌ — لا يُقرأ نجاحًا\n"); }
exit($FAIL === 0 ? 0 : 1);
