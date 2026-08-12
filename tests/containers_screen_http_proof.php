<?php
/**
 * tests/containers_screen_http_proof.php — H-01 المرحلة ②
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لشاشة حاويات العقود من **الشاشة الحقيقية**:
 *   ① التشغيلُ (1) يفتحها ويرى الشجرةَ بأرصدتها الستة.
 *   ② التوليدُ الرجعيُّ من الشاشة، و**وسمُ «مشتقّة» ظاهرٌ بملاحظته**.
 *   ③ **تجاوزُ Σ يُرَدُّ برسالةٍ تسمّي المتاحَ والمطلوب** — لا «حدث خطأ».
 *      (درسُ E-08-أ: لا يكفي ردٌّ صحيحٌ لا يراه أحد.)
 *   ④ التوزيعُ المشروع يقع ويظهر أثرُه في الأرصدة.
 *   ⑤ تبديلٌ بلا سببٍ يُرَدّ · وبسببه يقع.
 *   ⑥ الإقرارُ يرفع الوسم.
 *   ⑦ تقريرُ المطابقة يُعلن ما لا بندَ له.
 *   ⑧ **مَن لا يملكها لا يراها** (الدور 12 عرضًا · بلا أزرار إدارة).
 *
 * يكنس كلَّ ما ينشئه.
 * التشغيل: php tests/containers_screen_http_proof.php  (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
function info($m) { fwrite(STDOUT, "     · {$m}\n"); }

$JAR = null;
function c_req($url, $post = null) {
    global $JAR;
    $GLOBALS['__ems_last_url'] = $url;   // لحلِّ Location النسبيّ عند قراءةِ الوميض
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 45));
    if ($post !== null) {
        /* حاجزان لا حاجزٌ واحد: رمزُ الشاشةِ `cnt_csrf` (يكشفه `c_csrf`) **و**
           الرمزُ المركزيُّ `csrf_token` الذي يفحصه `includes/security.php:404`
           على كلِّ POST. والفاحصُ يبني حقولَه يدويًّا فيحمل الأوّلَ ويُسقط الثاني
           — فتُردُّ 403 ويُقرأ صمتُها «لم تُكتب حاويةٌ». */
        require_once __DIR__ . '/_http_flash.php';
        $post = ems_http_with_csrf($post, $url, $JAR);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
}
function c_login($user, $tag) {
    global $JAR, $BASE;
    $JAR = sys_get_temp_dir() . '/ems_cnt_' . $tag . '.txt'; @unlink($JAR);
    list(, , $b) = c_req($BASE . '/login.php');
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    list($c) = c_req($BASE . '/login.php', array('username' => $user, 'password' => '12345678',
        'csrf_token' => $m[1] ?? ''));
    return ($c === 200 || $c === 302);
}
function c_csrf($body) {
    return preg_match('~name="cnt_csrf"\s+value="([^"]+)"~', $body, $m) ? $m[1] : '';
}
/** الرسالةُ في وميضِ الجلسةِ أو في العنوان — يُقرأ الاثنان (انظر `_http_flash.php`). */
function c_msg($h) {
    require_once __DIR__ . '/_http_flash.php';
    $dir = isset($GLOBALS['__ems_last_url'])
        ? preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url'])
        : '';
    return ems_flash_or_msg($h, $dir, function ($u) {
        list(, , $b) = c_req($u, null);
        return (string) $b;
    });
}

$conn = $GLOBALS['conn'];
// البوابةُ fail-closed تحتاج سياقَ مستأجرٍ — ولا جلسةَ في CLI
$_SESSION['user'] = array('id' => 1, 'role' => '1', 'company_id' => 4, 'name' => 'cnt proof');
$gate = ems_tenant_db();
$CO = 4; $C5 = 5;
require_once dirname(__DIR__) . '/app/Services/Operations/OperationalTransformService.php';
$URL = $BASE . '/Operations/containers.php?contract=' . $C5;

/* ═══════════════════════════════════════════════════════════════════════════
   لا كنسَ جماعيًّا — والكنسُ الذي كان **لم يعمل أصلًا**
   ───────────────────────────────────────────────────────────────────────────
   كان هنا `DELETE FROM op_containers WHERE level=…` لكلِّ المستويات: كنسٌ يمحو
   شجرةَ الحاوياتِ **كلَّها** لكلِّ العقودِ ليصنع لوحًا أبيض. و**قياسُ المُرجَع
   أثبت أن الأربعةَ كلَّها تفشل صامتةً**: `مشغّل` يردُّها
   `substitute_coverages.fk_cov_seat`، والبواقي يردُّها FK الأبوَّةِ الذاتيُّ
   `fk_container_parent` — و`config.php` يضبط mysqli على **عدم الرمي**، فيُقرأ
   الفشلُ نجاحًا. النتيجة: 845 صفًّا يبقى، والفاحصُ يفترض لوحًا أبيضَ فيسقط،
   **وصفوفُه هو تتراكم**: ستُّ حاوياتٍ للمورّد 99 (لا وجودَ له في `suppliers`)
   حُشرت في العقد 5 فرفعت `allocated_qty` لأمِّها 600 وحدةً وهميّة.

   ⇒ فالبديلُ: **لا لوحَ أبيض**. تُقاس الحالةُ القائمةُ وتُحكَم الفروقُ (delta)،
     ويُعكَس ما يُنشئه هذا الشوطُ وحدَه — بمُرجَعٍ مفحوصٍ لكلِّ عكسٍ.
   ═══════════════════════════════════════════════════════════════════════════ */
$SUP = 99;   // مورّدُ البرهان — لا وجودَ له في `suppliers` فصفوفُه لا تلتبس ببيانات

/** يُبلّغ عن كلِّ حذفٍ/تحديثٍ يفشل بدل أن يبتلعه (mysqli لا يرمي هنا). */
$verified = function ($sql) use ($conn) {
    $ok = $conn->query($sql);
    if ($ok === false) {
        fwrite(STDERR, '  ⚠️ عكسٌ فشل: ' . $conn->error . ' ← ' . $sql . "\n");
        return false;
    }
    return true;
};

/* الحالةُ قبل أيِّ فعلٍ — إليها يُعاد كلُّ شيء */
$BASE_SWAPS = (int) $conn->query("SELECT COUNT(*) c FROM container_swaps")->fetch_assoc()['c'];
$BASE_MAXSW = (int) $conn->query("SELECT COALESCE(MAX(id),0) m FROM container_swaps")->fetch_assoc()['m'];
$BASE_MAXCN = (int) $conn->query("SELECT COALESCE(MAX(id),0) m FROM op_containers")->fetch_assoc()['m'];

/** أفعالُ إرجاعٍ يسجّلها الشوطُ وقتَ ما يُحدِث أثرًا — تُنفَّذ معكوسةَ الترتيب. */
$RESTORE = array();

register_shutdown_function(function () use ($conn, $verified, $SUP, $BASE_MAXSW, $BASE_MAXCN, &$RESTORE) {
    /* ① ما سجّله الشوطُ صراحةً (حالاتُ صفوفٍ مسَّها) — آخرًا أوّلًا */
    foreach (array_reverse($RESTORE) as $sql) { $verified($sql); }

    /* ② صفوفُ التبديلِ التي أنشأها هذا الشوطُ وحدَه */
    $verified("DELETE FROM container_swaps WHERE id > {$BASE_MAXSW}");

    /* ③ كلُّ حاويةٍ وُلدت في هذا الشوط: حصةُ مورّدِ البرهان **و**الحاويةُ
          البديلةُ التي يفتحها النقلُ الذريُّ. ويُعاد إلى الأمِّ ما زِيد عليها،
          وإلا بقي `allocated_qty` منتفخًا بحصةٍ لا حاويةَ لها.
          الأبناءُ قبل الآباء — وإلا ردَّ `fk_container_parent` الحذفَ صامتًا. */
    $r = $conn->query("SELECT id, parent_id, cap_qty, supplier_id FROM op_containers
                       WHERE id > {$BASE_MAXCN} ORDER BY id DESC");
    $born = array();
    while ($r && $row = $r->fetch_assoc()) { $born[] = $row; }
    foreach ($born as $row) {
        $pid  = (int) $row['parent_id'];
        $back = (float) $row['cap_qty'];
        if (!$verified("DELETE FROM op_containers WHERE id = " . (int) $row['id'])) { continue; }
        /* حصةُ التوزيعِ اليدويِّ وحدَها زادت رصيدَ الأمِّ — والنقلُ الذريُّ يُحرّك
           رصيدًا قائمًا فلا يُخصَم مرتين. */
        if ($pid > 0 && (int) $row['supplier_id'] === $SUP) {
            $verified("UPDATE op_containers SET allocated_qty = GREATEST(0, allocated_qty - {$back})
                       WHERE id = {$pid}");
        }
    }
});


fwrite(STDOUT, "\n══ H-01 ② — شاشةُ الحاويات من الشاشة الحقيقية ══\n");

// ═══ ① الفتحُ والأرصدة ═══
head('① التشغيل (1) — الشجرةُ بأرصدتها');
$u1 = $conn->query("SELECT username FROM users WHERE role=1 AND company_id={$CO} LIMIT 1")->fetch_assoc();
check($u1 && c_login($u1['username'], 'ops'), 'دخولُ «' . ($u1['username'] ?? '—') . '»');
list($c0, , $p0) = c_req($URL);
check($c0 === 200, 'الشاشةُ صُيِّرت (200)');
/* الشاشةُ لها **حالان** وكلاهما صحيح: شجرةٌ إن كانت حاوياتٌ، ولافتةُ «لا حاوياتٍ
   بعد» إن لم تكن. والفاحصُ كان يفترض الثانيَ دائمًا — لأنه كان يمحو الجدولَ قبله
   (ومحوُه لم يعمل). فيُقاس الحالُ ويُحكَم عليه، لا على لوحٍ أبيضَ مفترَض. */
$have5 = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                             WHERE contract_id={$C5} AND is_deleted=0")->fetch_assoc()['c'];
info("حاوياتُ العقد {$C5} القائمة: {$have5}");
if ($have5 === 0) {
    check(mb_strpos($p0, 'لا حاوياتٍ لهذا العقد بعد') !== false,
        'وتقول إنه بلا حاوياتٍ بعد — لا جدولَ فارغٌ يوهم');
} else {
    check(mb_strpos($p0, 'شجرةُ الحاويات') !== false && mb_strpos($p0, 'لا حاوياتٍ لهذا العقد بعد') === false,
        "وتصيّر الشجرةَ لا لافتةَ الفراغ (وله {$have5} حاويةً)");
}
check(mb_strpos($p0, 'ولّد الرئيسيات من بنود العقد') !== false, 'وأزرارُ الإدارة ظاهرةٌ لمالكها');
$CSRF = c_csrf($p0);
check($CSRF !== '', 'ورمزُ الحماية حاضر');

// ═══ ② التوليدُ الرجعي ووسمُ «مشتقّة» ═══
head('② التوليدُ الرجعيُّ — والوسمُ ظاهرٌ بملاحظته');
list(, $h1) = c_req($URL, array('cnt_action' => 'derive', 'contract_id' => $C5, 'cnt_csrf' => $CSRF));
$m1 = c_msg($h1);
check(mb_strpos($m1, 'مشتقّة') !== false, 'الرسالةُ تقول إنها مشتقّة: ' . mb_substr($m1, 0, 90));
check(mb_strpos($m1, 'تنتظر إقرارَ الإدارة') !== false, 'وتقول إنها تنتظر الإقرار');

/* الشارةُ المعلَّقةُ لا تظهر إلا لحاويةٍ مشتقّةٍ **بلا إقرار**. ومشتقّاتُ العقد 5
   مُقرَّةٌ كلُّها (مقيسٌ)، والتوليدُ عطِلٌ فلا ينشئ جديدًا — فالفاحصُ كان يطلب شارةً
   لا سبيلَ لظهورها. فيُرفَع الإقرارُ عن حاويةٍ واحدةٍ **رجعةً**: تُثبَت الشارةُ
   المعلَّقة، ثم يُقرّها الفعلُ من الشاشةِ في ⑥ فتنقلب الشارةُ ويعود الصفُّ كما
   كان بلا بقايا. وبهذا يبرهن الشوطُ على **الحالتين** لا على واحدةٍ محظوظة. */
$PENDCN = $conn->query("SELECT id, origin_ack_by FROM op_containers
                        WHERE contract_id={$C5} AND origin='مشتقّة' AND is_deleted=0
                        ORDER BY (origin_ack_by IS NULL) DESC, id LIMIT 1")->fetch_assoc();
if ($PENDCN && $PENDCN['origin_ack_by'] !== null) {
    $conn->query("UPDATE op_containers SET origin_ack_by=NULL, origin_ack_at=NULL
                  WHERE id=" . (int) $PENDCN['id']);
    info('رُفع الإقرارُ رجعةً عن الحاوية #' . $PENDCN['id'] . ' — يُعاد بفعلِ الشاشةِ في ⑥');
}
check($PENDCN !== null, 'وللعقد حاويةٌ مشتقّةٌ يُحكم على شارتها');

list(, , $p1) = c_req($URL);
check(mb_strpos($p1, 'مشتقّة — تنتظر الإقرار') !== false, '**والشارةُ ظاهرةٌ في الشجرة**');
check(mb_strpos($p1, 'مشتقّةٌ من صفوف التشغيل') !== false, 'وملاحظتُها تقول من أين اشتُقّت');
check(mb_strpos($p1, 'الحصةُ قرارٌ تجاريّ') !== false, 'والشاشةُ تشرح لماذا لا تُقدَّم متفقًا عليها');
check(preg_match_all('~CNT-\d{4}-\d{4}~', $p1) >= 15, 'والشجرةُ مصيَّرةٌ بحاوياتها');
foreach (array('السقف', 'الموزَّع', 'المتاح للتوزيع', 'المستهلَك', 'المتبقي') as $col) {
    check(mb_strpos($p1, $col) !== false, "  وعمودُ «{$col}» حاضر");
}

// ═══ ③ تجاوزُ Σ — الرسالةُ تسمّي ═══
head('③ تجاوزُ Σ — رسالةٌ تسمّي المتاحَ والمطلوب');
$main = $conn->query("SELECT id, cap_qty, allocated_qty FROM op_containers
                       WHERE contract_id={$C5} AND level='رئيسية' ORDER BY id LIMIT 1")->fetch_assoc();
$free = round((float) $main['cap_qty'] - (float) $main['allocated_qty'], 2);
$KIDS_BEFORE = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                                   WHERE parent_id=" . (int) $main['id'] . " AND supplier_id={$SUP}
                                     AND is_deleted=0")->fetch_assoc()['c'];
list(, $h2) = c_req($URL, array('cnt_action' => 'allocate', 'contract_id' => $C5,
    'parent_id' => (int) $main['id'], 'child_level' => 'مورد', 'child_ref' => 99,
    'qty' => $free + 100, 'cnt_csrf' => $CSRF));
$m2 = c_msg($h2);
check(mb_strpos($m2, 'تتجاوز المتاحَ') !== false, 'الرسالةُ تقول «تتجاوز المتاح»');
check(mb_strpos($m2, number_format($free, 2)) !== false,
    'و**تسمّي المتاحَ بالرقم** (' . number_format($free, 2) . '): ' . mb_substr($m2, 0, 110));
check(mb_strpos($m2, 'حدث خطأ') === false, 'ولا «حدث خطأ» — الرقمُ لا الغموض');
/* **الفرقُ لا العددُ المطلق**: كان الفحصُ يطلب صفرَ ابنٍ للمورّد 99 مطلقًا، فكان
   يعدُّ بقايا الأشواطِ السابقةِ (④ يُنشئ واحدًا في كلِّ شوط) ويسقط كذبًا كلَّ مرة.
   المحكومُ عليه هو **أثرُ هذا الطلبِ وحدَه**: أن لا يزيد عددُ الأبناءِ بواحد. */
$kidsAfter = (int) $conn->query("SELECT COUNT(*) c FROM op_containers
                                 WHERE parent_id=" . (int) $main['id'] . " AND supplier_id={$SUP}
                                   AND is_deleted=0")->fetch_assoc()['c'];
check($kidsAfter === $KIDS_BEFORE,
    "ولا حاويةَ كُتبت (قبل {$KIDS_BEFORE} · بعد {$kidsAfter})");

// ═══ ④ التوزيعُ المشروع ═══
head('④ التوزيعُ المشروع يقع ويظهر أثرُه');
list(, $h3) = c_req($URL, array('cnt_action' => 'allocate', 'contract_id' => $C5,
    'parent_id' => (int) $main['id'], 'child_level' => 'مورد', 'child_ref' => 99,
    'qty' => 100, 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h3), 'خُصّصت الحصة') !== false, 'وقع التوزيع');
$after = $conn->query("SELECT allocated_qty FROM op_containers WHERE id=" . (int) $main['id'])->fetch_assoc();
check((float) $after['allocated_qty'] == round((float) $main['allocated_qty'] + 100, 2),
    'والأمُّ زادت 100: ' . $main['allocated_qty'] . ' ← ' . $after['allocated_qty']);
list(, , $p3) = c_req($URL);
check(mb_strpos($p3, number_format((float) $after['allocated_qty'], 2)) !== false,
    'والرقمُ الجديدُ معروضٌ في الشاشة');

// ═══ ⑤ التبديل ═══
head('⑤ تبديلٌ بلا سببٍ يُرَدّ');
/* ═══ الورقةُ تُختار بثلاثةِ شروطٍ **يعلنها الحارسُ نفسُه**، لا بـ`LIMIT 1` ═══
   `RotationSwapService` يردُّ ثلاثةَ ردودٍ مشروعة، وكلٌّ منها أسقط فحصًا:
     · من عقدٍ آخر  ⇒ «الحاويةُ غير موجودةٍ في نطاقك» (404) — كان الاختيارُ
       `WHERE level='مشغّل' LIMIT 1` بلا عقدٍ فوقع على حاويةٍ من العقد **0**.
     · غيرُ نشطة   ⇒ «الحاويةُ «معلَّقة» — الاستبدالُ للنشطة» (423) — والورقةُ
       كانت مجمَّدةً من شوطٍ سابقٍ لم يُرجِع ما جمّد.
     · رصيدُها صفر ⇒ «رصيدُ الحاوية صفرٌ — لا شيءَ يُنقل» (422) — وH-04 **نقلٌ**
       فلا معنى له بلا رصيد. وأوراقُ «مشغّل» في العقد 5 كلُّها صفرُ رصيد (مقيسٌ).
     · **رصيدُها الحرُّ صفر** ⇒ «لا رصيدَ حرًّا يُنقل (… موزَّعةٌ على أبنائها …)»:
       أوراقُ العقد 5 الحقيقيةُ **موزَّعةٌ بالكامل** على أبنائها، فلا حرَّ فيها.
       وما كان يُنجِح هذا الفحصَ سابقًا كان **بقايا فاحصٍ** (30 حاويةً · 18,700
       وحدةً وهميّةً) أُزيلت بالهجرة 2027_03_11 — فالفحصُ كان يقف على رملٍ.
   ⇒ فالشوطُ **يبني قابلَ الاستبدالِ بنفسِه من الشاشةِ نفسِها**: حصةُ معدةٍ تحت
     حاويةِ مورّدِ البرهانِ التي وُلدت في ④ — بسقفٍ حرٍّ كلِّه. فلا يعتمد على
     بياناتِ إنتاجٍ ولا على بقايا، ويُكنس مع شجرتِه في نهايةِ الشوط. */
$SUP99 = $conn->query("SELECT id, cap_qty FROM op_containers
                       WHERE parent_id=" . (int) $main['id'] . " AND supplier_id={$SUP}
                         AND is_deleted=0 ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($SUP99 !== null, 'وحاويةُ مورّدِ البرهانِ حاضرةٌ لِتُبنى تحتها ورقةٌ');
$EQREF = $conn->query("SELECT id FROM equipments WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc();
if ($SUP99 && $EQREF) {
    list(, $hEq) = c_req($URL, array('cnt_action' => 'allocate', 'contract_id' => $C5,
        'parent_id' => (int) $SUP99['id'], 'child_level' => 'معدة', 'child_ref' => (int) $EQREF['id'],
        'qty' => 60, 'role_kind' => 'أساسية', 'cnt_csrf' => $CSRF));
    check(mb_strpos(c_msg($hEq), 'خُصّصت الحصة') !== false, 'وحصةُ معدةٍ (60) بُنيت تحتها للاستبدالِ عليها');
}
$leaf = $conn->query("SELECT id, level, remaining_qty, equipment_id, operator_employee_id
                      FROM op_containers
                      WHERE contract_id={$C5} AND is_deleted=0 AND state='نشطة'
                        AND level IN ('معدة','مشغّل')
                        AND ROUND(cap_qty - consumed_qty - allocated_qty, 2) > 0
                      ORDER BY id DESC LIMIT 1")->fetch_assoc();
check($leaf !== null, 'وللعقد قابلةُ استبدالٍ نشطةٌ برصيدٍ موجب');
$SWAP_LVL = $leaf ? (string) $leaf['level'] : 'مشغّل';
$HOLD_COL = ($SWAP_LVL === 'معدة') ? 'equipment_id' : 'operator_employee_id';
$REF_TBL  = ($SWAP_LVL === 'معدة') ? 'equipments' : 'employees';
$OUT_REF  = $leaf ? (int) $leaf[$HOLD_COL] : 0;
/* الحائزُ الداخلُ **موجودٌ في النطاق وغيرُ الخارج** — وإلا رُدَّ بحقٍّ أيضًا. */
$inRow = $conn->query("SELECT id FROM {$REF_TBL} WHERE company_id={$CO} AND id <> {$OUT_REF}
                       ORDER BY id LIMIT 1")->fetch_assoc();
$IN_REF = $inRow ? (int) $inRow['id'] : 0;
check($IN_REF > 0, "وحائزٌ داخلٌ من «{$REF_TBL}» غيرُ الخارج (#{$OUT_REF} ← #{$IN_REF})");
info("الاستبدالُ على «{$SWAP_LVL}» #" . ($leaf['id'] ?? '—') . ' برصيد ' . ($leaf['remaining_qty'] ?? '—'));
/* النقلُ الذريُّ **يجمّد** هذه الورقةَ ويحوّل رصيدَها — أثرٌ حقيقيٌّ في صفٍّ قائمٍ
   لا في صفٍّ وُلد. فتُسجَّل حالتُه وحائزُه ورصيدُه للإرجاع، وإلا تركَ الشوطُ
   حاويةً مجمَّدةً في بياناتٍ حقيقيةٍ كلَّ مرةٍ يُشغَّل. */
if ($leaf) {
    /* و`cap_qty` **من ضمن ما يُرجَع**: التجميدُ يُنزل السقفَ إلى
       `consumed + allocated`، فمن أرجع الحالةَ وحدَها تركَ Σسقوفِ الجدولِ ناقصًا
       بمقدارِ ما نُقل — نقصٌ لا يظهر في أيِّ عدِّ صفوفٍ (مقيسٌ: −300 لكلِّ شوط). */
    $pre = $conn->query("SELECT state, {$HOLD_COL} AS holder, cap_qty, allocated_qty, remaining_qty, close_reason
                         FROM op_containers WHERE id=" . (int) $leaf['id'])->fetch_assoc();
    if ($pre) {
        $RESTORE[] = "UPDATE op_containers SET state='" . $conn->real_escape_string($pre['state']) . "',"
            . " {$HOLD_COL}=" . ($pre['holder'] === null ? 'NULL' : (int) $pre['holder']) . ','
            . ' cap_qty=' . (float) $pre['cap_qty'] . ','
            . ' allocated_qty=' . (float) $pre['allocated_qty'] . ','
            . ' remaining_qty=' . (float) $pre['remaining_qty'] . ','
            . ' close_reason=' . ($pre['close_reason'] === null ? 'NULL' : "'" . $conn->real_escape_string($pre['close_reason']) . "'")
            . ' WHERE id=' . (int) $leaf['id'];
    }
}
list(, $h4) = c_req($URL, array('cnt_action' => 'swap', 'contract_id' => $C5,
    'container_id' => (int) $leaf['id'], 'swap_kind' => $SWAP_LVL,
    'out_ref' => $OUT_REF, 'in_ref' => $IN_REF,
    'effective_from' => '2027-01-01', 'reason' => '', 'cnt_csrf' => $CSRF));
// نصُّ الحارسِ في `RotationSwapService`: «سببُ **الاستبدال** إلزامي — بقرارٍ موثَّق».
// وكان الفاحصُ يطلب «سببُ التبديل» — لفظًا لم يعد المنتجُ يقوله.
check(mb_strpos(c_msg($h4), 'سببُ الاستبدال إلزامي') !== false, 'مرفوضٌ برسالته');
list(, $h5) = c_req($URL, array('cnt_action' => 'swap', 'contract_id' => $C5,
    'container_id' => (int) $leaf['id'], 'swap_kind' => $SWAP_LVL,
    'out_ref' => $OUT_REF, 'in_ref' => $IN_REF,
    'effective_from' => '2027-01-01', 'reason' => 'إجازةٌ اضطرارية', 'cnt_csrf' => $CSRF));
/* **H-04 غيَّر الفعلَ نفسَه**: الاستبدالُ لم يبقَ سجلًّا وصفيًّا — صار **نقلًا
   ذريًّا**: «تُجمَّد الخارجةُ عند رصيدها وتُفتح البديلةُ بالمتبقي». فالفاحصُ كان
   يطلب «سُجّل التبديلُ بسببه» — نصًّا مات. ويُحكَم على **الجوهرِ** لا اللفظ:
   الرسالةُ تسمّي الحاويةَ البديلةَ والكميةَ المنقولة، والخارجةُ تُصبح غيرَ نشطة. */
$m5 = c_msg($h5);
check(mb_strpos($m5, 'نُقل الرصيدُ ذريًّا') !== false, 'وبسببه يقع — نقلًا ذريًّا (H-04)');
check(preg_match('~إلى الحاوية #(\d+)~u', $m5, $mm5) === 1,
    'والرسالةُ تسمّي الحاويةَ البديلةَ بالرقم: ' . mb_substr($m5, 0, 90));
$outState = $conn->query("SELECT state FROM op_containers WHERE id=" . (int) $leaf['id'])->fetch_assoc();
check($outState && (string) $outState['state'] !== 'نشطة',
    'والخارجةُ لم تبقَ نشطةً — مجمَّدةٌ عند رصيدها: «' . ($outState['state'] ?? '—') . '»');
/* بالفرقِ أيضًا: سجلُّ التبديلِ تاريخٌ يتراكم، فالمطلقُ «واحدٌ» يكذب في الشوط الثاني. */
$sw = (int) $conn->query("SELECT COUNT(*) c FROM container_swaps")->fetch_assoc()['c'];
check($sw === $BASE_SWAPS + 1, "وصفٌّ واحدٌ زِيد في سجل التبديل (قبل {$BASE_SWAPS} · بعد {$sw})");

// ═══ ⑥ الإقرار ═══
head('⑥ الإقرارُ يرفع الوسم');
/* الحاويةُ نفسُها التي رُفع إقرارُها في ② — لا أوّلَ معلَّقٍ في الجدولِ كلِّه
   (فقد كان يقع على عقدٍ آخرَ فيردُّه الحارسُ بحق). وبإقرارها هنا يعود الصفُّ
   إلى حالتِه الأولى، فلا يترك الشوطُ أثرًا. */
$pend = $PENDCN ? array('id' => $PENDCN['id'])
                : $conn->query("SELECT id FROM op_containers
                                WHERE contract_id={$C5} AND origin='مشتقّة'
                                  AND origin_ack_by IS NULL AND is_deleted=0 LIMIT 1")->fetch_assoc();
list(, $h6) = c_req($URL, array('cnt_action' => 'acknowledge', 'contract_id' => $C5,
    'container_id' => (int) $pend['id'], 'cnt_csrf' => $CSRF));
check(mb_strpos(c_msg($h6), 'أُقرّت الحصة') !== false, 'أُقرّت من الشاشة');
$row = $conn->query("SELECT origin_ack_by FROM op_containers WHERE id=" . (int) $pend['id'])->fetch_assoc();
check((int) $row['origin_ack_by'] > 0, 'وباسم مَن أقرّها: #' . $row['origin_ack_by']);
list(, , $p6) = c_req($URL);
check(mb_strpos($p6, 'مشتقّةٌ ومُقرَّة') !== false, 'والشارةُ انقلبت «مشتقّةٌ ومُقرَّة»');

// ═══ ⑦ تقريرُ المطابقة ═══
head('⑦ تقريرُ المطابقة يُعلن ما لا بندَ له');
check(mb_strpos($p6, 'تقريرُ المطابقة') !== false, 'التقريرُ حاضر');
check(mb_strpos($p6, 'وقائعُ بوحدةٍ لا حاويةَ رئيسيةً لها') !== false, 'وقسمُ الوحدات بلا بند');
check(mb_strpos($p6, 'تنتظر بندًا أو ملحقًا') !== false, 'وحكمُها منصوص');
check(mb_strpos($p6, 'حصصٌ مشتقّةٌ تنتظر الإقرار') !== false, 'وقسمُ المشتقّات المعلَّقة');

// ═══ ⑧ مَن لا يملكها ═══
head('⑧ مَن لا يملك الإدارةَ لا يرى أزرارَها');
$u12 = $conn->query("SELECT username FROM users WHERE role=12 AND company_id={$CO} LIMIT 1")->fetch_assoc();
if ($u12) {
    check(c_login($u12['username'], 'sales'), 'دخولُ «' . $u12['username'] . '» (الدور 12 — عرضًا)');
    list($c8, , $p8) = c_req($URL);
    check($c8 === 200 && mb_strpos($p8, 'شجرةُ الحاويات') !== false, 'ويرى الشجرة');
    check(mb_strpos($p8, 'ولّد الرئيسيات من بنود العقد') === false, '**ولا يرى زرَّ التوليد**');
    // ⚠️ `cnt-alloc-btn` يظهر في كتلة الـJS مهما كانت الصلاحية — فالفحصُ به يمرّ
    //    كذبًا. العلامةُ الفارقةُ سمةُ الزرِّ نفسِه.
    check(mb_strpos($p8, 'data-parent=') === false, 'ولا زرَّ التوزيع (لا سمةَ data-parent)');
    list(, $h8) = c_req($URL, array('cnt_action' => 'derive', 'contract_id' => $C5, 'cnt_csrf' => 'x'));
    check(mb_strpos(c_msg($h8), 'صلاحيةُ إدارة التشغيل') !== false,
        'وفعلُه يُردُّ ولو أُرسل يدويًّا');
} else { ok('(لا مستخدمَ بالدور 12 — يُتخطّى)'); }

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
