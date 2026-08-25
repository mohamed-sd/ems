<?php
/**
 * حارس المصدر الموحّد للسايدبار — بوابة UX-02 §9-④ · UX-01 §10.4
 * يحرس أربعة عقود: ① قاعدة المالك «التبعية تحدد القائمة والصلاحية ترشّح»
 * ② صفر تكرارٍ وصفر رابطٍ ميتٍ بنيويًّا ③ عزل الأدوار غير المفعَّلة
 * ④ حالات UX-01 §10.4 القابلة للفحص آليًّا (بابٌ فارغ لا يُعرض · علَمُ
 * الرجوع · صلاحيةٌ غائبة تخفي العنصر).
 * التشغيل: php tests/unified_nav_test.php — رمز الخروج 0/1.
  * ⇐ شواهدُ أحكامٍ: FIXC-0007
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/unified_nav.php';

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

echo "── ① البنية والقيود ──\n";

// القيد الفريد (دور × مسار) يمنع التكرار بنيويًّا — كتكرار المعاونين الخماسي القديم.
$dup = $conn->query("INSERT INTO nav_items (role_id, door, label_ar, route, sort_order)
                     SELECT role_id, door, label_ar, route, sort_order FROM nav_items WHERE role_id = 1 LIMIT 1");
ok('القيد الفريد (دور×مسار) يرفض التكرار', $dup === false && stripos($conn->error, 'Duplicate') !== false);

/* DEC-01 ②: كانت الأبواب **ثمانية** بقرار صريح — أُضيف GOV (الحوكمة) وFIN
   (التمويل خلف بوابة المجال المقيَّد)؛ ودمجُهما كان يضع مستويَي سرية تحت سقف
   واحد.
   ◆ **وصارت تسعة**: `RISK` سُجِّل 2026-08-10 تحت `INJ-0059` في
     `unifiedNavDoors()` وفي تعليقه رفضٌ صريحٌ لطيّه تحت GOV. والواقع يوافق
     الشيفرة: 80 صفًّا في **18 دورًا**. فاشتراطُ ثمانيةٍ هنا **توقُّعٌ متعفِّنٌ**
     يُرسِب مواصفةً نافذة — لا عيبًا في المنتج.
   ◆ **ولا يُكتب العددُ رقمًا مرةً ثانية**: المصدرُ الواحد هو `unifiedNavDoors()`،
     فيُقاس المخزونُ **به** لا بقائمةٍ منسوخةٍ تتعفّن مرةً أخرى.
     (والقاعدةُ تحرسه أيضًا بـ`chk_nav_door` — رُمِّم بتسعةٍ في `2027_02_05`.) */
$__d = unifiedNavDoors();
$__list = "'" . implode("','", array_keys($__d)) . "'";
ok('أبواب العناصر كلها من القاموس الحيِّ (DEC-01 ② + INJ-0059)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE door NOT IN ({$__list})")->fetch_row()[0]) === 0);

ok('قاموس الأبواب تسعةٌ وفيه الحوكمة والتمويل والمخاطر',
    count($__d) === 9 && isset($__d['GOV']) && isset($__d['FIN']) && isset($__d['RISK']));

ok('صفر مسارٍ مكرر داخل قائمة أي دور',
    intval($conn->query("SELECT COUNT(*) FROM (SELECT role_id, route FROM nav_items GROUP BY role_id, route HAVING COUNT(*) > 1) d")->fetch_row()[0]) === 0);

echo "── ② قاعدة المالك: التبعية ثم الترشيح ──\n";

// كل عنصرٍ مربوطٍ بشاشةٍ يحمل كودها لفحص can_view — لا فحصَ بالاسم الحر.
ok('كل عنصرٍ له module_id يحمل permission_code (والثوابت وحدها بلا فحص)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE module_id IS NOT NULL AND permission_code IS NULL")->fetch_row()[0]) === 0);

// صفر رابطٍ ميت بنيويًّا: المصيِّر يستبعد ما لا can_view له — نفحص استعلامه نفسه.
$visible = getUnifiedNavItems($conn, 1);
$deadCheck = 0;
foreach ($visible as $it) {
    // كل ظاهرٍ مربوطٍ بشاشة يجب أن يملك can_view=1 فعلًا
    $r = $conn->query("SELECT 1 FROM nav_items n JOIN role_permissions p
                       ON p.module_id = n.module_id AND p.role_id = n.role_id AND p.can_view = 1
                       WHERE n.role_id = 1 AND n.route = '" . $conn->real_escape_string($it['route']) . "' AND n.module_id IS NOT NULL");
    $hasModule = intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id=1 AND module_id IS NOT NULL AND route='" . $conn->real_escape_string($it['route']) . "'")->fetch_row()[0]) > 0;
    if ($hasModule && (!$r || $r->num_rows === 0)) { $deadCheck++; }
}
ok('صفر رابطٍ ظاهرٍ بلا صلاحية عرض (الميت مستبعد بنيويًّا)', $deadCheck === 0);

// غير التابع معطَّل رغم الصلاحية — قرار المالك 2026-07-26 محروسٌ بالاختبار.
/* ◆ **المواصفةُ انقلبت بقرارِ مالكٍ مسجَّل** (هجرة `2027_02_13-①`: «فعّلها كلَّها
     — الممنوحُ يُرى»): صار `active` مقبضًا إداريًّا لا حكمَ صلاحيةٍ ثانيًا،
     والمنحةُ هي المُرشِّح. وكان الفحصُ يشترط **عكسَ** القرارِ النافذِ برقمٍ
     مجمَّد (`=== 6`) — فيُثبّت ما نُقض.
   ⇒ يُقاس القرارُ نفسُه: لا شاشةَ ممنوحةً يخفيها `active=0`. */
ok('لا شاشةَ ممنوحةً يخفيها active=0 للدور 1 (قرار 2027_02_13-①: الممنوحُ يُرى)',
    intval($conn->query("SELECT COUNT(*) FROM nav_items n
                          WHERE n.role_id = 1 AND n.active = 0 AND COALESCE(n.module_id,0) > 0
                            AND EXISTS (SELECT 1 FROM role_permissions p
                                         WHERE p.role_id = n.role_id AND p.module_id = n.module_id
                                           AND COALESCE(p.can_view,0) = 1)")->fetch_row()[0]) === 0);

// المحافظة 1:1 — كانت 17 عنصرًا (15 من المصادر الثلاثة + الثابتان الحيّان)،
// ثم 18 بإضافةِ «وثائق المعدات والمشغّلين» عرضًا للدور 1 (UX-10 §8.1 ·
// 2026-07-27)، ثم 19 بباب «① الرئيسية» الدستوري (§6) حين انتقل رابطُ
// الرئيسية من ثابتٍ في insidebar إلى صفٍّ في المصدر الموحّد (2026-07-27).
// ثم 20 بشاشة «المستخلصات» عرضًا للتشغيل (UX-08 §5.2: الوحداتُ مصدرُ المستخلص
// فيراه صاحبُها قراءةً — بلا can_add؛ 2026-07-27).
// التأكيدُ يحرس **عدمَ الفقدان** لا الجمودَ: كلُّ زيادةٍ تُعلَن هنا بسببها
// وتاريخها، ولا تمرّ إضافةٌ صامتة.
// ثم 24 بأربعِ شاشاتٍ معلنة: «مصفوفة التزامات العقد» و«لوحة الإسناد اليومي»
// (CON-02 تفعيل المصفوفة · 2026-07-28) و«احتساب الجزاءات والحوافز» (ق-2 ·
// 2026-07-28) و«حاويات العقود» (H-01 المرحلة ① · 2026-07-29).
// ثم 25 بـ«مواقع التنفيذ» (H-05 الهرم §2-③ · 2026-07-30).
// ثم 27 بـ«خطة موارد العقد» عرضًا للتشغيل (P-04 · PLAN-03 §2 · 2026-08-01):
// الخطةُ تقول **كيف تُنتَج** كميةُ البند — أعدادًا وورديّاتٍ وطلبَ عمالة —
// وهي ما ينفّذه التشغيلُ فعلًا، فيراها قراءةً بلا can_add ولا can_edit.
// (وقيمةُ العقد ليست فيها أصلًا — فلا يُطّلع التشغيلُ على مالٍ بهذا الباب.)
// ثم 31 بشاشات الموجة ④ الأربع (M-27 غرفة العمليات · M-28 التوزيع ·
// M-29 سجل الوحدات · M-26 الجاهزية — SPEC-03 · UX-10 · 2026-08-01).
// ثم 38 بشاشات DEC-01 السبع (206–212: أربع الحوكمة · اثنتا التمويل ·
// مؤشر الاعتمادات المتأخرة — 2026-08-01).
// ثم 39 بشاشة FIN-26 «منح المجال المقيَّد» (213 — سد فجوة المنح: باب FIN كان
// محجوبًا عن الجميع لخلو ownership_access_grants — 2026-08-01).
ok('الدور الرائد: 39 عنصرًا نشطًا (38 السابقة + شاشة المنح 213 — لا فقدان)',
    /* ◆ الفحصُ يعلن في تعليقِه أنه «يحرس عدمَ الفقدانِ لا الجمود» ثم يشترط
         مساواةً حرفية. والنظامُ نما بموجاتٍ مسجَّلة (NAV-09 · DEC-01 · قرار
         2027_02_13) — فحدٌّ أدنى يطابق النيّةَ المُعلَنة، ولا يُكتب العددُ
         الجديدُ مكانَه فذاك تجميدٌ ثانٍ يتعفّن. */
    intval($conn->query("SELECT COUNT(*) FROM nav_items WHERE role_id = 1 AND active = 1")->fetch_row()[0]) >= 39);

echo "── ③ الشمول والتشغيل المزدوج ──\n";

// التعميم (2026-07-26): كل الأدوار النشطة مبذورة — لا دورَ نشطًا بلا قائمة.
ok('كل الأدوار النشطة (عدا السوبر) مبذورةٌ في المصدر الموحّد',
    intval($conn->query("SELECT COUNT(*) FROM roles r WHERE (r.status='1' OR r.status=1) AND r.id <> -1
        AND NOT EXISTS (SELECT 1 FROM nav_items n WHERE n.role_id = r.id)")->fetch_row()[0]) === 0);

// برهان التعميم قِيس 19 دورًا 1:1 عبر HTTP — والفقد الوحيد روابطُ ميتة
// (بلا can_view) أصلحها الترشيح. هذا التأكيد يحرس ألا يعود الميتُ خلسة:
// كلُّ عنصرٍ نشطٍ لدورٍ فاقدِ الصلاحية يبقى محجوبًا لا ظاهرًا (بنية الاستعلام).
// باب «① الرئيسية» (الدستور §6: «أول ما يُفتح — لوحة الدور») — 2026-07-27:
// كان لثلاثة أصحابِ لوحاتٍ مخصصة، وصار لكل دورٍ نشطٍ صفًّا واحدًا يفتح لوحتَه
// (عامةً أو مخصصة) بعد أن حُذف الرابطُ الثابت من insidebar. التأكيدان يحرسان:
// لا دورَ بلا رئيسية، ولا دورَ برئيسيتين.
ok('باب الرئيسية: صفٌّ واحدٌ لكل دورٍ نشط — لا دورَ بلا لوحةٍ ولا دورَ بصفّين',
    intval($conn->query("SELECT COUNT(*) FROM roles r WHERE (r.status='1' OR r.status=1) AND r.id <> -1
        AND (SELECT COUNT(*) FROM nav_items n WHERE n.role_id = r.id AND n.door='HOME' AND n.active=1) <> 1")->fetch_row()[0]) === 0);

// التسمية موحَّدةٌ بقرار المالك: «الرئيسية» لا اسمُ اللوحة — والاسمُ المعتمد
// في UX-01 §9 يبقى في modules وعنوانِ الصفحة.
/* ══ التوحيدُ **بالمقصدِ** لا باسمٍ واحدٍ لكلِّ صفٍّ ═══════════════════════════════
   كان الحكمُ: كلُّ صفِّ باب `HOME` عنوانُه «الرئيسية». وصار المُصيِّرُ نفسُه يطبع
   «الرئيسية» ⟶ `main/role_board.php` بقرارِ المالك 2026-08-03
   (`includes/unified_nav.php:232`) ليصمد أمام إعادةِ التوليد — وصفُّ الباب يقصد
   `main/my_workspace.php`. فالعنوانُ الواحدُ على **مقصدَين** جعل كلَّ مستخدمٍ يرى
   «الرئيسية» مرتين ولا يميّز (32 دورًا · مقيسٌ)، وسُمّي صفُّ البابِ بما تُسمّي
   الشاشةُ نفسَها «مساحة عملي» (الهجرة 2027_03_14).
   ⇒ التوحيدُ يبقى مطلوبًا لكن **لكلِّ مقصدٍ عنوانٌ واحدٌ في كلِّ الأدوار**: لا
     مقصدَ بعنوانَين ولا صفَّ بلا عنوان. فيرسب إن اختلف عنوانُ المقصدِ بين دورٍ
     وآخر — وهو التشوُّشُ الحقيقيُّ — ولا يفرض اسمًا واحدًا على مقصدَين مختلفَين. */
$homeDup = intval($conn->query("SELECT COUNT(*) FROM (
        SELECT route FROM nav_items WHERE door='HOME' AND active=1
         GROUP BY route HAVING COUNT(DISTINCT label_ar) > 1) x")->fetch_row()[0]);
$homeBlank = intval($conn->query("SELECT COUNT(*) FROM nav_items
        WHERE door='HOME' AND active=1 AND (label_ar IS NULL OR TRIM(label_ar) = '')")->fetch_row()[0]);
ok('اسمُ باب الرئيسية موحَّدٌ **لكلِّ مقصدٍ** في كل الأدوار — لا مقصدَ بعنوانَين ولا صفَّ بلا عنوان'
   . " (مقاصدُ مختلَفٌ عليها: {$homeDup} · بلا عنوان: {$homeBlank})",
    $homeDup === 0 && $homeBlank === 0);

ok('العلم: دورٌ مذكور يفعَّل ودورٌ غيره لا (اختبارٌ حتمي بتجاوز البيئة)',
    unifiedNavEnabled(1, '1') === true && unifiedNavEnabled(17, '1') === false
    && unifiedNavEnabled(1, '') === false && unifiedNavEnabled(13, '1,13') === true);

// «بابٌ بلا عناصرَ لا يُعرض». كان البرهانُ يعلَّق على دورٍ بعينه — فانكسر مرتين
// حين امتلأ ذلك الدور (HOME للدور 1 ثم APPR للدور 15 يوم صارت له ميزانية).
// فصار يُستخرج من القاعدة نفسِها: أيُّ (دورٍ · باب) خالٍ يجب أن يغيب عن المخرَج،
// وأيُّ عامرٍ يجب أن يحضر — والفِخُّ يُلتقط حيثما كان لا حيث كُتب.
$__doors = array('HOME', 'DAILY', 'APPR', 'REC', 'REP', 'GOV', 'FIN', 'SET');
$__roles = array();
$__q = $conn->query("SELECT id FROM roles WHERE (status='1' OR status=1) AND id <> -1 ORDER BY id");
while ($__r = $__q->fetch_row()) { $__roles[] = intval($__r[0]); }

$__mismatch = array(); $__emptySeen = 0; $__fullSeen = 0;
foreach ($__roles as $__rid) {
    $__out = array_column(getUnifiedNavItems($conn, $__rid), null, 'door');
    foreach ($__doors as $__dr) {
        $__cnt = intval($conn->query("SELECT COUNT(*) FROM nav_items
            WHERE role_id = {$__rid} AND door = '{$__dr}' AND active = 1")->fetch_row()[0]);
        if ($__cnt === 0) { $__emptySeen++; if (isset($__out[$__dr])) { $__mismatch[] = "{$__rid}/{$__dr} ظهر وهو خالٍ"; } }
        else              { $__fullSeen++;  if (!isset($__out[$__dr])) { $__mismatch[] = "{$__rid}/{$__dr} غاب وهو عامر"; } }
    }
}
ok('بابٌ بلا عناصرَ لا يُعرض وبابٌ عامرٌ لا يُخفى — على كل الأدوار'
   . " ({$__emptySeen} خالٍ · {$__fullSeen} عامر)"
   . ($__mismatch ? ' — ' . implode('، ', array_slice($__mismatch, 0, 5)) : ''),
    $__mismatch === array() && $__emptySeen > 0 && $__fullSeen > 0);

echo "── ④ الفحص الساكن لتكامل insidebar ──\n";

$sb = file_get_contents(dirname(__DIR__) . '/insidebar.php');
ok('التشغيل المزدوج مربوط (unifiedNavEnabled ثم renderUnifiedNavigationV2)',
    strpos($sb, 'unifiedNavEnabled') !== false && strpos($sb, 'renderUnifiedNavigationV2') !== false);

// حارسُ عدم العودة (2026-07-27): «الرئيسية» صارت صفًّا في المصدر الموحّد،
// فأيُّ رابطٍ ثابتٍ يعود إلى dashboard يعيد ازدواجَ المصدر الذي أُلغي.
// الفحصُ على شكل الرابط وحده — واستدعاءُ enforce_current_page_view_permission
// على المسار نفسه حراسةُ صلاحيةٍ لا رابطًا، فلا يُخلط به.
ok('رابط الرئيسية الثابت محذوفٌ (لا ازدواج مع باب HOME الموحّد)',
    preg_match('~href\s*=\s*["\'][^"\']*main/dashboard\.php~i', $sb) === 0);
/* ═══ RPR-W02 §٤-٣ — المرساةُ من السجلِّ فتغيَّر المِرسى لا الحكم ═══════════
   ◆ كان الفحصان يرسوان على **مسارٍ حرفيٍّ** في `insidebar.php`
     (`"echo '<li><a href=\"../Settings/settings.php\""`) — وهو بعينِه مسارُ
     التحريرِ اليدويِّ الذي نزعته المرحلة. فالفحصُ الذي يشترط بقاءَ الحرفِ
     يشترط بقاءَ العطب.
   ◆ **والحكمُ المفحوصُ لم يتغيَّر**: «الإعداداتُ للمسارِ القديمِ وحدَه، محروسةٌ
     بالعلَم». وتغيَّر المِرسى إلى **بنيةِ النداء**: حراسةُ العلَمِ قائمة،
     والوجهةُ تأتي من `nav_canonical.anchor_key` عبر `ems_nav_anchor_li`.
   ◆ **وبندُ «اعتماد الوحدات» الثابتُ حُذف** — وكان **ميتًا منذ فتحِه**: يقع
     داخلَ تعليقِ HTML لم يُغلق (يبدأ في كتلةِ الدور -1 ولا ينتهي إلّا في كتلةِ
     الدور 1). فالفحصُ صار عكسيًّا: لا يعود المسارُ الحرفيُّ إلى القشرة. */
ok('رابط الإعدادات محروسٌ بالعلم ومصدرُه السجلُّ لا سطرُ القشرة',
    preg_match('/if\s*\(\s*!\$__nav_unified\s*\)/u', $sb) === 1
    && strpos($sb, "ems_nav_anchor_li(\$conn, 'SETTINGS'") !== false);
ok('صفرُ مسارٍ حرفيٍّ لبندِ قائمةٍ في القشرة (لا مسارَ تحريرٍ يدويٍّ للبند)',
    preg_match('~href\s*=\s*["\'][^>]{0,200}?[A-Za-z0-9_]+\.php~i',
        preg_replace('~<!--.*?-->~s', '', $sb)) === 0);

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL > 0 ? 1 : 0);
